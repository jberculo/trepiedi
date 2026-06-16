<?php

namespace App\Controller;

use App\Entity\FootballMatch;
use App\Entity\User;
use App\Repository\FootballMatchRepository;
use App\Repository\PoolRepository;
use App\Repository\UserRepository;
use App\Scoring\ScoringService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Publieke JSON-API.
 *   - Lezen (geen sleutel): stand per poule en het wedstrijdoverzicht.
 *   - Schrijven (X-API-Key, alleen beheerders): uitslag toevoegen/aanpassen van
 *     wedstrijden die nog open staan (niet definitief).
 */
class ApiController extends AbstractController
{
    /**
     * Huidige stand van een poule. Zonder code: de standaardpoule.
     */
    #[Route('/api/standings', name: 'api_standings_default', methods: ['GET'])]
    #[Route('/api/standings/{code}', name: 'api_standings', methods: ['GET'])]
    public function standings(
        ?string $code,
        Request $request,
        PoolRepository $pools,
        ScoringService $scoringService,
    ): JsonResponse {
        // Code mag via het pad of via ?pool=…; zonder code de standaardpoule.
        $code ??= $request->query->get('pool');
        $pool = ($code !== null && $code !== '')
            ? $pools->findOneActiveByCode($code)
            : $pools->findDefault();
        if ($pool === null) {
            return $this->json(['error' => 'Onbekende of gearchiveerde poule.'], Response::HTTP_NOT_FOUND);
        }

        $memberIds = [];
        foreach ($pool->getMembers() as $member) {
            if ($member->getId() !== null) {
                $memberIds[] = $member->getId();
            }
        }

        $standings = [];
        foreach ($scoringService->buildLeaderboard(null, $memberIds) as $entry) {
            $standings[] = [
                'rank' => $entry->rank,
                'player' => $entry->user->getDisplayName(),
                'slug' => $entry->user->getSlug(),
                'weightedTotal' => $entry->weightedTotal,
                'rawTotal' => $entry->rawTotal,
                'scorePoints' => $entry->scorePoints,
                'winners' => $entry->advanceCount,
                'lanternPoints' => $entry->lanternPoints,
                'inconsistent' => $entry->inconsistentCount,
            ];
        }

        return $this->json([
            'pool' => ['name' => $pool->getName(), 'code' => $pool->getCode()],
            'standings' => $standings,
        ]);
    }

    /**
     * Alle wedstrijden, met uitslag en of ze nog open staan (chronologisch).
     */
    #[Route('/api/matches', name: 'api_matches', methods: ['GET'])]
    public function matches(FootballMatchRepository $matches): JsonResponse
    {
        $data = [];
        foreach ($matches->findAllChronological() as $match) {
            $data[] = $this->matchToArray($match);
        }

        return $this->json(['matches' => $data]);
    }

    /**
     * Uitslag van een open wedstrijd toevoegen/aanpassen. Vereist een geldige
     * API-sleutel van een beheerder (header X-API-Key).
     */
    #[Route('/api/matches/{id}/result', name: 'api_match_result', methods: ['POST', 'PUT'])]
    public function setResult(
        FootballMatch $match,
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
    ): JsonResponse {
        $key = (string) $request->headers->get('X-API-Key', '');
        if ($key === '') {
            return $this->json(['error' => 'API-sleutel ontbreekt (header X-API-Key).'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $users->findOneByApiToken($key);
        if (!$user instanceof User) {
            return $this->json(['error' => 'Ongeldige API-sleutel.'], Response::HTTP_UNAUTHORIZED);
        }
        if (!$user->isAdmin()) {
            return $this->json(['error' => 'Alleen beheerders mogen uitslagen aanpassen.'], Response::HTTP_FORBIDDEN);
        }

        if ($match->isFinished()) {
            return $this->json(['error' => 'Deze wedstrijd is definitief en staat niet meer open.'], Response::HTTP_CONFLICT);
        }

        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return $this->json(['error' => 'Ongeldige JSON-body.'], Response::HTTP_BAD_REQUEST);
        }

        $errors = [];
        $homeScore = $this->intOrNull($body['homeScore'] ?? null);
        $awayScore = $this->intOrNull($body['awayScore'] ?? null);
        if ($homeScore === null || $homeScore < 0) {
            $errors[] = 'homeScore is verplicht en moet 0 of hoger zijn.';
        }
        if ($awayScore === null || $awayScore < 0) {
            $errors[] = 'awayScore is verplicht en moet 0 of hoger zijn.';
        }

        $advancingSide = $body['advancingSide'] ?? null;
        if ($advancingSide !== null && !in_array($advancingSide, [FootballMatch::SIDE_HOME, FootballMatch::SIDE_AWAY], true)) {
            $errors[] = 'advancingSide moet "home" of "away" zijn.';
        }

        $finished = (bool) ($body['finished'] ?? false);
        if ($finished && $advancingSide === null) {
            $errors[] = 'Voor een definitieve uitslag is advancingSide ("home"/"away") verplicht.';
        }

        if ($errors !== []) {
            return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $match->setHomeScore($homeScore);
        $match->setAwayScore($awayScore);
        $match->setAdvancingSide($advancingSide);
        $match->setFinished($finished);
        $em->flush();

        return $this->json($this->matchToArray($match));
    }

    /**
     * @return array<string, mixed>
     */
    private function matchToArray(FootballMatch $match): array
    {
        return [
            'id' => $match->getId(),
            'round' => $match->getRound()?->getName(),
            'kickoff' => $match->getKickoffAt()?->format(\DateTimeInterface::ATOM),
            'home' => $match->getHomeTeam(),
            'away' => $match->getAwayTeam(),
            'homeScore' => $match->getHomeScore(),
            'awayScore' => $match->getAwayScore(),
            'advancingTeam' => $match->getAdvancingTeam(),
            'advancingSide' => $match->getAdvancingSide(),
            'finished' => $match->isFinished(),
            'open' => !$match->isFinished(),
        ];
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }
}
