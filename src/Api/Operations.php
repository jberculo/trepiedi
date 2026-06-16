<?php

namespace App\Api;

use App\Entity\FootballMatch;
use App\Entity\Pool;
use App\Entity\Prediction;
use App\Entity\Round;
use App\Entity\User;
use App\Pool\PoolCodeGenerator;
use App\Reference\Countries;
use App\Repository\FootballMatchRepository;
use App\Repository\PoolRepository;
use App\Repository\PredictionRepository;
use App\Repository\RoundRepository;
use App\Scoring\ScoringService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * De domeinlogica achter de API én de MCP-server: lezen (stand/wedstrijden/ronden)
 * en schrijven (eigen voorspelling, en als beheerder uitslagen/wedstrijden/poules).
 * Geeft data-arrays terug en gooit ApiException (met status) bij fouten, zodat de
 * REST- en MCP-laag allebei dezelfde code gebruiken.
 */
class Operations
{
    public function __construct(
        private PoolRepository $pools,
        private FootballMatchRepository $matches,
        private PredictionRepository $predictions,
        private RoundRepository $rounds,
        private ScoringService $scoring,
        private PoolCodeGenerator $codeGenerator,
        private EntityManagerInterface $em,
    ) {
    }

    // ── Lezen ────────────────────────────────────────────────────────────────

    public function standings(?string $code): array
    {
        $pool = ($code !== null && $code !== '') ? $this->pools->findOneActiveByCode($code) : $this->pools->findDefault();
        if ($pool === null) {
            throw new ApiException(404, 'Onbekende of gearchiveerde poule.');
        }

        $standings = [];
        // Met positieverandering sinds de vorige speeldag (movement: + = gestegen).
        foreach ($this->scoring->leaderboardWithMovement($this->memberIds($pool)) as $entry) {
            $standings[] = [
                'rank' => $entry->rank,
                'movement' => $entry->rankChange['points'] ?? null,
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

        return [
            'pool' => ['name' => $pool->getName(), 'code' => $pool->getCode()],
            'types' => $this->rankingTypes(),
            'standings' => $standings,
        ];
    }

    /**
     * De klassement-types met hun emoji en het bijbehorende veld in de stand.
     *
     * @return list<array{key: string, emoji: string, label: string, field: string}>
     */
    public function rankingTypes(): array
    {
        return [
            ['key' => 'points', 'emoji' => '🟡', 'label' => 'Algemeen', 'field' => 'weightedTotal'],
            ['key' => 'score', 'emoji' => '⚽', 'label' => 'Balletjestrui', 'field' => 'scorePoints'],
            ['key' => 'winners', 'emoji' => '🔮', 'label' => 'Glazen bal', 'field' => 'winners'],
            ['key' => 'lantern', 'emoji' => '🔴', 'label' => 'Ronde lantaarn', 'field' => 'lanternPoints'],
            ['key' => 'inconsistent', 'emoji' => '🤔', 'label' => 'Tegenstrijdig', 'field' => 'inconsistent'],
        ];
    }

    public function matchesList(): array
    {
        return ['matches' => array_map($this->matchToArray(...), $this->matches->findAllChronological())];
    }

    public function matchDetail(int $id): array
    {
        $match = $this->findMatch($id);
        $data = $this->matchToArray($match);
        $data['predictionCount'] = count($this->predictions->findByMatch($match));

        if ($match->isLocked()) {
            $data['predictions'] = array_map(static fn (Prediction $p): array => [
                'player' => $p->getUser()->getDisplayName(),
                'homeScore' => $p->getHomeScore(),
                'awayScore' => $p->getAwayScore(),
                'advancingSide' => $p->getAdvancingSide(),
            ], $this->predictions->findByMatch($match));
        }

        return $data;
    }

    public function roundsList(): array
    {
        $data = [];
        foreach ($this->rounds->findBy([], ['sortOrder' => 'ASC']) as $round) {
            /** @var Round $round */
            $data[] = [
                'name' => $round->getName(),
                'sortOrder' => $round->getSortOrder(),
                'weight' => $round->getWeight(),
                'matchCount' => count($round->getMatches()),
            ];
        }

        return ['rounds' => $data];
    }

    // ── Met je eigen sleutel ───────────────────────────────────────────────────

    public function me(?User $user): array
    {
        $user = $this->requireUser($user);

        $pools = [];
        foreach ($user->getPools() as $pool) {
            $pools[] = ['name' => $pool->getName(), 'code' => $pool->getCode(), 'default' => $pool->isDefault(), 'archived' => $pool->isArchived()];
        }

        return [
            'displayName' => $user->getDisplayName(),
            'slug' => $user->getSlug(),
            'admin' => $user->isAdmin(),
            'pools' => $pools,
            'activePool' => $user->getActivePool()?->getCode(),
        ];
    }

    public function submitPrediction(?User $user, int $matchId, array $body): array
    {
        $user = $this->requireUser($user);
        $match = $this->findMatch($matchId);

        if (!$match->isActive()) {
            throw new ApiException(409, 'Deze wedstrijd is nog niet te voorspellen.');
        }
        if ($match->isLocked()) {
            throw new ApiException(409, 'Deze wedstrijd is begonnen; je voorspelling kan niet meer worden aangepast.');
        }

        [$homeScore, $awayScore] = $this->requireScores($body);
        $advancingSide = $body['advancingSide'] ?? null;
        if (!in_array($advancingSide, [FootballMatch::SIDE_HOME, FootballMatch::SIDE_AWAY], true)) {
            throw new ApiException(422, 'advancingSide is verplicht en moet "home" of "away" zijn.');
        }

        $prediction = $this->predictions->findOneForUserAndMatch($user, $match) ?? new Prediction();
        $prediction->setUser($user)->setFootballMatch($match)
            ->setHomeScore($homeScore)->setAwayScore($awayScore)->setAdvancingSide($advancingSide)
            ->setUpdatedAt(new \DateTimeImmutable());
        $this->em->persist($prediction);
        $this->em->flush();

        return ['match' => $match->getId(), 'prediction' => ['homeScore' => $homeScore, 'awayScore' => $awayScore, 'advancingSide' => $advancingSide], 'saved' => true];
    }

    // ── Beheerder ───────────────────────────────────────────────────────────────

    public function setResult(?User $user, int $matchId, array $body): array
    {
        $this->requireAdmin($user);
        $match = $this->findMatch($matchId);
        if ($match->isFinished()) {
            throw new ApiException(409, 'Deze wedstrijd is definitief en staat niet meer open.');
        }

        [$homeScore, $awayScore] = $this->requireScores($body);
        $advancingSide = $body['advancingSide'] ?? null;
        if ($advancingSide !== null && !in_array($advancingSide, [FootballMatch::SIDE_HOME, FootballMatch::SIDE_AWAY], true)) {
            throw new ApiException(422, 'advancingSide moet "home" of "away" zijn.');
        }
        $finished = (bool) ($body['finished'] ?? false);
        if ($finished && $advancingSide === null) {
            throw new ApiException(422, 'Voor een definitieve uitslag is advancingSide ("home"/"away") verplicht.');
        }

        $match->setHomeScore($homeScore)->setAwayScore($awayScore)->setAdvancingSide($advancingSide)->setFinished($finished);
        $this->em->flush();

        return $this->matchToArray($match);
    }

    public function updateMatch(?User $user, int $matchId, array $body): array
    {
        $this->requireAdmin($user);
        $match = $this->findMatch($matchId);

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
                throw new ApiException(422, 'Ongeldige kickoff (gebruik bijv. 2026-06-28T21:00:00).');
            }
        }

        $this->em->flush();

        return $this->matchToArray($match);
    }

    public function poolsList(?User $user): array
    {
        $this->requireAdmin($user);

        $data = [];
        foreach ($this->pools->findAllForAdmin() as $pool) {
            $data[] = ['name' => $pool->getName(), 'code' => $pool->getCode(), 'default' => $pool->isDefault(), 'archived' => $pool->isArchived(), 'members' => count($pool->getMembers())];
        }

        return ['pools' => $data];
    }

    public function createPool(?User $user, array $body): array
    {
        $this->requireAdmin($user);

        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            throw new ApiException(422, 'name is verplicht.');
        }

        $code = isset($body['code']) && $body['code'] !== '' ? strtolower((string) $body['code']) : $this->codeGenerator->generate($name);
        if ($this->pools->findOneByCode($code) !== null) {
            throw new ApiException(409, 'Er bestaat al een poule met deze code.');
        }

        $pool = (new Pool())->setName($name)->setCode($code);
        if (!empty($body['default'])) {
            foreach ($this->pools->findBy(['isDefault' => true]) as $other) {
                $other->setDefault(false);
            }
            $pool->setDefault(true);
        }
        $this->em->persist($pool);
        $this->em->flush();

        return ['name' => $pool->getName(), 'code' => $pool->getCode(), 'default' => $pool->isDefault()];
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    private function findMatch(int $id): FootballMatch
    {
        $match = $this->matches->find($id);
        if ($match === null) {
            throw new ApiException(404, 'Wedstrijd niet gevonden.');
        }

        return $match;
    }

    private function requireUser(?User $user): User
    {
        if (!$user instanceof User) {
            throw new ApiException(401, 'Ontbrekende of ongeldige API-sleutel (header X-API-Key).');
        }

        return $user;
    }

    private function requireAdmin(?User $user): User
    {
        $user = $this->requireUser($user);
        if (!$user->isAdmin()) {
            throw new ApiException(403, 'Alleen beheerders mogen dit.');
        }

        return $user;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function requireScores(array $body): array
    {
        $home = $this->intOrNull($body['homeScore'] ?? null);
        $away = $this->intOrNull($body['awayScore'] ?? null);
        if ($home === null || $home < 0) {
            throw new ApiException(422, 'homeScore is verplicht en moet 0 of hoger zijn.');
        }
        if ($away === null || $away < 0) {
            throw new ApiException(422, 'awayScore is verplicht en moet 0 of hoger zijn.');
        }

        return [$home, $away];
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
            // flag-icons-code per ploeg (null bij een onbekende naam/placeholder).
            'homeFlag' => Countries::codeForName($match->getHomeTeam()),
            'awayFlag' => Countries::codeForName($match->getAwayTeam()),
            'homeScore' => $match->getHomeScore(),
            'awayScore' => $match->getAwayScore(),
            'advancingTeam' => $match->getAdvancingTeam(),
            'advancingFlag' => Countries::codeForName($match->getAdvancingTeam()),
            'advancingSide' => $match->getAdvancingSide(),
            'finished' => $match->isFinished(),
            'open' => !$match->isFinished(),
            'active' => $match->isActive(),
            'locked' => $match->isLocked(),
            'predictable' => $match->isActive() && !$match->isLocked(),
        ];
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }
}
