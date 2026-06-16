<?php

namespace App\Controller;

use App\Entity\FootballMatch;
use App\Entity\Pool;
use App\Entity\Prediction;
use App\Entity\User;
use App\Pool\PoolCodeGenerator;
use App\Repository\FootballMatchRepository;
use App\Repository\PoolRepository;
use App\Repository\PredictionRepository;
use App\Repository\RoundRepository;
use App\Repository\UserRepository;
use App\Scoring\ScoringService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * JSON-API.
 *   - Lezen (geen sleutel): stand, wedstrijden, ronden.
 *   - Met je persoonlijke sleutel (header X-API-Key): je eigen voorspelling en /api/me.
 *   - Met een beheerderssleutel: uitslagen, wedstrijden bijwerken, poules beheren.
 */
class ApiController extends AbstractController
{
    // ── Lezen (publiek) ────────────────────────────────────────────────────────

    #[Route('/api/standings', name: 'api_standings_default', methods: ['GET'])]
    #[Route('/api/standings/{code}', name: 'api_standings', methods: ['GET'])]
    public function standings(?string $code, Request $request, PoolRepository $pools, ScoringService $scoringService): JsonResponse
    {
        // Code mag via het pad of via ?pool=…; zonder code de standaardpoule.
        $code ??= $request->query->get('pool');
        $pool = ($code !== null && $code !== '') ? $pools->findOneActiveByCode($code) : $pools->findDefault();
        if ($pool === null) {
            return $this->json(['error' => 'Onbekende of gearchiveerde poule.'], Response::HTTP_NOT_FOUND);
        }

        $standings = [];
        foreach ($scoringService->buildLeaderboard(null, $this->memberIds($pool)) as $entry) {
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

    #[Route('/api/matches', name: 'api_matches', methods: ['GET'])]
    public function matches(FootballMatchRepository $matches): JsonResponse
    {
        $data = array_map($this->matchToArray(...), $matches->findAllChronological());

        return $this->json(['matches' => $data]);
    }

    #[Route('/api/matches/{id}', name: 'api_match', methods: ['GET'])]
    public function match(FootballMatch $match, PredictionRepository $predictions): JsonResponse
    {
        $data = $this->matchToArray($match);
        $data['predictionCount'] = count($predictions->findByMatch($match));

        // Voorspellingen worden pas zichtbaar zodra de wedstrijd is begonnen.
        if ($match->isLocked()) {
            $data['predictions'] = array_map(static fn (Prediction $p): array => [
                'player' => $p->getUser()->getDisplayName(),
                'homeScore' => $p->getHomeScore(),
                'awayScore' => $p->getAwayScore(),
                'advancingSide' => $p->getAdvancingSide(),
            ], $predictions->findByMatch($match));
        }

        return $this->json($data);
    }

    #[Route('/api/rounds', name: 'api_rounds', methods: ['GET'])]
    public function rounds(RoundRepository $rounds): JsonResponse
    {
        $data = [];
        foreach ($rounds->findBy([], ['sortOrder' => 'ASC']) as $round) {
            $data[] = [
                'name' => $round->getName(),
                'sortOrder' => $round->getSortOrder(),
                'weight' => $round->getWeight(),
                'matchCount' => count($round->getMatches()),
            ];
        }

        return $this->json(['rounds' => $data]);
    }

    // ── Met je persoonlijke sleutel ─────────────────────────────────────────────

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(Request $request, UserRepository $users): JsonResponse
    {
        $user = $this->userForApiKey($request, $users);
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        $pools = [];
        foreach ($user->getPools() as $pool) {
            $pools[] = ['name' => $pool->getName(), 'code' => $pool->getCode(), 'default' => $pool->isDefault(), 'archived' => $pool->isArchived()];
        }

        return $this->json([
            'displayName' => $user->getDisplayName(),
            'slug' => $user->getSlug(),
            'admin' => $user->isAdmin(),
            'pools' => $pools,
            'activePool' => $user->getActivePool()?->getCode(),
        ]);
    }

    /**
     * Je eigen voorspelling toevoegen/aanpassen voor een wedstrijd die nog te
     * voorspellen is (actief én niet begonnen).
     */
    #[Route('/api/matches/{id}/prediction', name: 'api_match_prediction', methods: ['POST', 'PUT'])]
    public function setPrediction(
        FootballMatch $match,
        Request $request,
        UserRepository $users,
        PredictionRepository $predictions,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = $this->userForApiKey($request, $users);
        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        if (!$match->isActive()) {
            return $this->json(['error' => 'Deze wedstrijd is nog niet te voorspellen.'], Response::HTTP_CONFLICT);
        }
        if ($match->isLocked()) {
            return $this->json(['error' => 'Deze wedstrijd is begonnen; je voorspelling kan niet meer worden aangepast.'], Response::HTTP_CONFLICT);
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
        if (!in_array($advancingSide, [FootballMatch::SIDE_HOME, FootballMatch::SIDE_AWAY], true)) {
            $errors[] = 'advancingSide is verplicht en moet "home" of "away" zijn.';
        }
        if ($errors !== []) {
            return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $prediction = $predictions->findOneForUserAndMatch($user, $match) ?? new Prediction();
        $prediction->setUser($user);
        $prediction->setFootballMatch($match);
        $prediction->setHomeScore($homeScore);
        $prediction->setAwayScore($awayScore);
        $prediction->setAdvancingSide($advancingSide);
        $prediction->setUpdatedAt(new \DateTimeImmutable());
        $em->persist($prediction);
        $em->flush();

        return $this->json([
            'match' => $match->getId(),
            'prediction' => ['homeScore' => $homeScore, 'awayScore' => $awayScore, 'advancingSide' => $advancingSide],
            'saved' => true,
        ]);
    }

    // ── Beheerderssleutel ───────────────────────────────────────────────────────

    /**
     * Uitslag van een open (niet-definitieve) wedstrijd toevoegen/aanpassen.
     */
    #[Route('/api/matches/{id}/result', name: 'api_match_result', methods: ['POST', 'PUT'])]
    public function setResult(FootballMatch $match, Request $request, UserRepository $users, EntityManagerInterface $em): JsonResponse
    {
        $admin = $this->adminForApiKey($request, $users);
        if (!$admin instanceof User) {
            return $this->forbiddenOrUnauthorized($request, $users);
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
     * Wedstrijd bijwerken: ploegnamen invullen, activeren/deactiveren of de aftrap
     * aanpassen. Handig om de bracket te vullen zodra de loting bekend is.
     */
    #[Route('/api/matches/{id}', name: 'api_match_update', methods: ['PATCH'])]
    public function updateMatch(FootballMatch $match, Request $request, UserRepository $users, EntityManagerInterface $em): JsonResponse
    {
        $admin = $this->adminForApiKey($request, $users);
        if (!$admin instanceof User) {
            return $this->forbiddenOrUnauthorized($request, $users);
        }

        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return $this->json(['error' => 'Ongeldige JSON-body.'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($body['home'])) {
            $match->setHomeTeam((string) $body['home']);
        }
        if (isset($body['away'])) {
            $match->setAwayTeam((string) $body['away']);
        }
        if (array_key_exists('active', $body)) {
            $match->setActive((bool) $body['active']);
        }
        if (isset($body['kickoff'])) {
            try {
                $match->setKickoffAt(new \DateTimeImmutable((string) $body['kickoff']));
            } catch (\Exception) {
                return $this->json(['error' => 'Ongeldige kickoff (gebruik bijv. 2026-06-28T21:00:00).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $em->flush();

        return $this->json($this->matchToArray($match));
    }

    #[Route('/api/pools', name: 'api_pools', methods: ['GET'])]
    public function pools(Request $request, UserRepository $users, PoolRepository $poolRepo): JsonResponse
    {
        if (!$this->adminForApiKey($request, $users) instanceof User) {
            return $this->forbiddenOrUnauthorized($request, $users);
        }

        $data = [];
        foreach ($poolRepo->findAllForAdmin() as $pool) {
            $data[] = [
                'name' => $pool->getName(),
                'code' => $pool->getCode(),
                'default' => $pool->isDefault(),
                'archived' => $pool->isArchived(),
                'members' => count($pool->getMembers()),
            ];
        }

        return $this->json(['pools' => $data]);
    }

    #[Route('/api/pools', name: 'api_pool_create', methods: ['POST'])]
    public function createPool(Request $request, UserRepository $users, PoolRepository $poolRepo, PoolCodeGenerator $codeGenerator, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->adminForApiKey($request, $users) instanceof User) {
            return $this->forbiddenOrUnauthorized($request, $users);
        }

        $body = json_decode($request->getContent(), true);
        $name = is_array($body) ? trim((string) ($body['name'] ?? '')) : '';
        if ($name === '') {
            return $this->json(['errors' => ['name is verplicht.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $code = isset($body['code']) && $body['code'] !== '' ? strtolower((string) $body['code']) : $codeGenerator->generate($name);
        if ($poolRepo->findOneByCode($code) !== null) {
            return $this->json(['errors' => ['Er bestaat al een poule met deze code.']], Response::HTTP_CONFLICT);
        }

        $pool = (new Pool())->setName($name)->setCode($code);
        if (!empty($body['default'])) {
            foreach ($poolRepo->findBy(['isDefault' => true]) as $other) {
                $other->setDefault(false);
            }
            $pool->setDefault(true);
        }
        $em->persist($pool);
        $em->flush();

        return $this->json(
            ['name' => $pool->getName(), 'code' => $pool->getCode(), 'default' => $pool->isDefault()],
            Response::HTTP_CREATED,
        );
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    private function userForApiKey(Request $request, UserRepository $users): ?User
    {
        $key = (string) $request->headers->get('X-API-Key', '');

        return $key !== '' ? $users->findOneByApiToken($key) : null;
    }

    private function adminForApiKey(Request $request, UserRepository $users): ?User
    {
        $user = $this->userForApiKey($request, $users);

        return $user instanceof User && $user->isAdmin() ? $user : null;
    }

    /**
     * 401 bij een ontbrekende/ongeldige sleutel, 403 bij een geldige niet-beheerder.
     */
    private function forbiddenOrUnauthorized(Request $request, UserRepository $users): JsonResponse
    {
        if ($this->userForApiKey($request, $users) instanceof User) {
            return $this->json(['error' => 'Alleen beheerders mogen dit.'], Response::HTTP_FORBIDDEN);
        }

        return $this->unauthorized();
    }

    private function unauthorized(): JsonResponse
    {
        return $this->json(['error' => 'Ontbrekende of ongeldige API-sleutel (header X-API-Key).'], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return list<int>
     */
    private function memberIds(Pool $pool): array
    {
        $ids = [];
        foreach ($pool->getMembers() as $member) {
            if ($member->getId() !== null) {
                $ids[] = $member->getId();
            }
        }

        return $ids;
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
            // 'open' = uitslag nog niet definitief (voor de uitslag-write).
            'open' => !$match->isFinished(),
            'active' => $match->isActive(),
            'locked' => $match->isLocked(),
            // 'predictable' = je kunt nu nog een voorspelling indienen/aanpassen.
            'predictable' => $match->isActive() && !$match->isLocked(),
        ];
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }
}
