<?php

namespace App\Scoring;

use App\Entity\FootballMatch;
use App\Entity\Prediction;
use App\Entity\Round;
use App\Entity\User;
use App\Repository\FootballMatchRepository;
use App\Repository\PredictionRepository;
use App\Repository\RoundRepository;
use App\Repository\UserRepository;

/**
 * Berekent punten volgens de poule-regels (vanaf de 16e finales):
 *   - 1 punt voor het juiste aantal doelpunten voor;
 *   - 1 punt voor het juiste aantal doelpunten tegen;
 *   - 1 extra punt als beide kloppen (exacte uitslag na reguliere speeltijd én verlenging, zonder penalty's);
 *   - 3 punten als je de winnaar (het team dat doorgaat) goed hebt.
 *
 * Elke wedstrijd telt standaard even zwaar (maximaal 6 punten). Het rondegewicht is
 * de expliciete vermenigvuldiger: de gewogen score is "punten × rondegewicht" (met
 * gewicht 1 dus gelijk aan de ruwe punten).
 *
 * Dure lookups (ronden, wedstrijden, voorspellingen, klassement) worden per request
 * gememoiseerd; de service is request-scoped en de data is read-only binnen een render.
 */
class ScoringService
{
    public const POINTS_PER_GOAL_SIDE = 1;
    public const EXACT_BONUS = 1;
    public const ADVANCE_POINTS = 3;

    /** Max raw points per match: 1 + 1 + 1 + 3 = 6. */
    public const MAX_RAW_PER_MATCH = self::POINTS_PER_GOAL_SIDE * 2 + self::EXACT_BONUS + self::ADVANCE_POINTS;

    /** Max lantern penalty per match: 2 reversed + 1 wrong winner. */
    public const MAX_LANTERN_PER_MATCH = 3;

    /** @var array<int, Round>|null */
    private ?array $roundsByIdCache = null;

    /** @var list<FootballMatch>|null */
    private ?array $allMatchesCache = null;

    /** @var list<FootballMatch>|null */
    private ?array $finishedMatchesCache = null;

    /** @var list<Prediction>|null */
    private ?array $scoringPredictionsCache = null;

    /** @var array<int, true>|null */
    private ?array $participantIdsCache = null;

    /** @var list<User>|null */
    private ?array $usersCache = null;

    /** @var array{count: array<int, int>, max: float, latest: ?\DateTimeImmutable}|null */
    private ?array $finishedSummaryCache = null;

    /** @var array{count: int, max: float}|null */
    private ?array $tournamentSummaryCache = null;

    /** @var array<string, list<LeaderboardEntry>> */
    private array $leaderboardCache = [];

    public function __construct(
        private PredictionRepository $predictionRepository,
        private FootballMatchRepository $footballMatchRepository,
        private RoundRepository $roundRepository,
        private UserRepository $userRepository,
        private int $matchdayBoundaryHour = 9,
    ) {
    }

    public function scorePrediction(Prediction $prediction): MatchScore
    {
        $match = $prediction->getFootballMatch();

        if ($match === null || !$match->hasResult()) {
            return new MatchScore(0, 0, 0, 0);
        }

        $home = ($prediction->getHomeScore() !== null && $prediction->getHomeScore() === $match->getHomeScore())
            ? self::POINTS_PER_GOAL_SIDE : 0;
        $away = ($prediction->getAwayScore() !== null && $prediction->getAwayScore() === $match->getAwayScore())
            ? self::POINTS_PER_GOAL_SIDE : 0;
        $bonus = ($home > 0 && $away > 0) ? self::EXACT_BONUS : 0;

        $advance = ($match->getAdvancingSide() !== null
            && $prediction->getAdvancingSide() !== null
            && $prediction->getAdvancingSide() === $match->getAdvancingSide())
            ? self::ADVANCE_POINTS : 0;

        return new MatchScore($home, $away, $bonus, $advance);
    }

    public function maxAchievableTotal(): float
    {
        return $this->finishedSummary()['max'];
    }

    public function maxTournamentTotal(): float
    {
        return $this->tournamentSummary()['max'];
    }

    public function tournamentMatchCount(): int
    {
        return $this->tournamentSummary()['count'];
    }

    /**
     * @param list<int>|null $userIds
     *
     * @return list<LeaderboardEntry>
     */
    public function buildLeaderboard(?\DateTimeImmutable $before = null, ?array $userIds = null): array
    {
        $cacheKey = $this->leaderboardCacheKey($before, $userIds);
        if (isset($this->leaderboardCache[$cacheKey])) {
            return $this->leaderboardCache[$cacheKey];
        }

        $rounds = $this->roundsById();
        $allowed = $userIds === null ? null : array_flip($userIds);
        $participantIds = $this->participantIds();

        /** @var array<int, LeaderboardEntry> $entries */
        $entries = [];
        foreach ($this->users() as $user) {
            if (!isset($participantIds[$user->getId()])) {
                continue;
            }
            if ($allowed !== null && !isset($allowed[$user->getId()])) {
                continue;
            }
            $entries[$user->getId()] = new LeaderboardEntry($user);
        }

        foreach ($this->scoringPredictions() as $prediction) {
            $user = $prediction->getUser();
            $match = $prediction->getFootballMatch();
            if ($user === null || $match === null || $match->getRound() === null) {
                continue;
            }

            $entry = $entries[$user->getId()] ?? null;
            if ($entry === null) {
                continue;
            }

            if ($before !== null && ($match->getKickoffAt() === null || $match->getKickoffAt() >= $before)) {
                continue;
            }

            $roundId = $match->getRound()->getId();
            $weight = ($rounds[$roundId] ?? null)?->getWeight() ?? 1.0;
            $score = $this->scorePrediction($prediction);
            $points = $score->total();
            $weighted = $points * $weight;

            $entry->rounds[$roundId]['raw'] = ($entry->rounds[$roundId]['raw'] ?? 0) + $points;
            $entry->rounds[$roundId]['weighted'] = ($entry->rounds[$roundId]['weighted'] ?? 0.0) + $weighted;
            $entry->rawTotal += $points;
            $entry->weightedTotal += $weighted;

            $entry->scorePoints += $score->homeGoalsPoint + $score->awayGoalsPoint + $score->exactBonusPoint;
            $entry->homeCorrect += $score->homeGoalsPoint;
            $entry->awayCorrect += $score->awayGoalsPoint;
            $entry->rounds[$roundId]['home'] = ($entry->rounds[$roundId]['home'] ?? 0) + $score->homeGoalsPoint;
            $entry->rounds[$roundId]['away'] = ($entry->rounds[$roundId]['away'] ?? 0) + $score->awayGoalsPoint;

            if ($score->exactBonusPoint > 0) {
                ++$entry->exactCount;
                $entry->rounds[$roundId]['exact'] = ($entry->rounds[$roundId]['exact'] ?? 0) + 1;
            }
            if ($score->advancePoints > 0) {
                ++$entry->advanceCount;
                $entry->rounds[$roundId]['advance'] = ($entry->rounds[$roundId]['advance'] ?? 0) + 1;
            }

            if ($match->hasResult() && $prediction->isInconsistent()) {
                ++$entry->inconsistentCount;
                $entry->rounds[$roundId]['inconsistent'] = ($entry->rounds[$roundId]['inconsistent'] ?? 0) + 1;
            }

            $penalty = $this->lanternPenalty($prediction);
            if ($penalty > 0) {
                $entry->lanternPoints += $penalty;
                $entry->rounds[$roundId]['lantern'] = ($entry->rounds[$roundId]['lantern'] ?? 0) + $penalty;
            }
        }

        $list = array_values($entries);
        usort($list, static function (LeaderboardEntry $a, LeaderboardEntry $b): int {
            return [$b->weightedTotal, $b->rawTotal, $a->user->getDisplayName()]
                <=> [$a->weightedTotal, $a->rawTotal, $b->user->getDisplayName()];
        });

        $ranks = Ranker::assign($list, static fn (LeaderboardEntry $e): float => $e->weightedTotal);
        foreach ($list as $i => $entry) {
            $entry->rank = $ranks[$i];
        }

        return $this->leaderboardCache[$cacheKey] = $list;
    }

    /**
     * @param list<int>|null $userIds
     *
     * @return list<LeaderboardEntry>
     */
    public function leaderboardWithMovement(?array $userIds = null): array
    {
        $current = $this->buildLeaderboard(null, $userIds);

        $cutoff = $this->lastMatchdayStart();
        if ($cutoff === null) {
            return $current;
        }

        // Alleen movement tonen als er echt een vorige speeldag was (afgeronde
        // wedstrijden vóór de cutoff). Niet afgaan op "iemand had punten": een
        // speeldag waarop iedereen 0 haalde is ook historie.
        if (!$this->hasFinishedMatchesBefore($cutoff)) {
            return $current;
        }

        $previous = $this->buildLeaderboard($cutoff, $userIds);

        $rankings = [
            'points' => static fn (LeaderboardEntry $e): array => [$e->weightedTotal, $e->rawTotal],
            'flat' => static fn (LeaderboardEntry $e): array => [$e->rawTotal],
            'score' => static fn (LeaderboardEntry $e): array => [$e->scorePoints, $e->weightedTotal],
            'winners' => static fn (LeaderboardEntry $e): array => [$e->advanceCount, $e->weightedTotal],
            'lantern' => static fn (LeaderboardEntry $e): array => [$e->lanternPoints, $e->weightedTotal],
            'inconsistent' => static fn (LeaderboardEntry $e): array => [$e->inconsistentCount, $e->weightedTotal],
        ];

        foreach ($rankings as $key => $metric) {
            $currentPos = $this->positionMap($current, $metric);
            $previousPos = $this->positionMap($previous, $metric);
            foreach ($current as $entry) {
                $uid = $entry->user->getId();
                if (isset($currentPos[$uid], $previousPos[$uid])) {
                    $entry->rankChange[$key] = $previousPos[$uid] - $currentPos[$uid];
                }
            }
        }

        return $current;
    }

    /**
     * @param list<int>|null $userIds
     */
    public function leaderboardEntryForUser(User $user, ?array $userIds = null): ?LeaderboardEntry
    {
        foreach ($this->buildLeaderboard(null, $userIds) as $entry) {
            if ($entry->user->getId() === $user->getId()) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Positie per speler in dit klassement, tie-aware: spelers met dezelfde
     * hoofdwaarde (eerste element van $metric) delen een positie, net als de
     * getoonde rang. Zonder dit zou een volledig gelijke stand spookbewegingen
     * opleveren (iedereen "1", maar pijlen die plaatsen aangeven).
     *
     * @param list<LeaderboardEntry> $entries
     * @param callable(LeaderboardEntry): list<int|float> $metric
     *
     * @return array<int, int>
     */
    private function positionMap(array $entries, callable $metric): array
    {
        $list = $entries;
        usort($list, static function (LeaderboardEntry $a, LeaderboardEntry $b) use ($metric): int {
            return [...$metric($b), $a->user->getDisplayName()] <=> [...$metric($a), $b->user->getDisplayName()];
        });

        $ranks = Ranker::assign($list, static fn (LeaderboardEntry $e): float => (float) $metric($e)[0]);

        $map = [];
        foreach ($list as $i => $entry) {
            $map[$entry->user->getId()] = $ranks[$i];
        }

        return $map;
    }

    /**
     * @param list<int>|null $userIds
     *
     * @return array{players: list<array{name: string, slug: ?string, avatar: ?string}>, steps: list<array<string, mixed>>}
     */
    public function matchTimeline(?array $userIds = null): array
    {
        $rounds = $this->roundsById();
        $players = $this->participantPlayers($userIds);
        $byMatch = $this->predictionsByMatch();

        $playerIds = array_keys($players);
        $steps = [];
        foreach ($this->finishedMatches() as $match) {
            $weight = $match->getRound() !== null
                ? (($rounds[$match->getRound()->getId()] ?? null)?->getWeight() ?? 1.0)
                : 1.0;

            $points = [];
            $flat = [];
            $score = [];
            $winners = [];
            $lantern = [];
            $inconsistent = [];

            foreach ($playerIds as $uid) {
                $prediction = $byMatch[$match->getId()][$uid] ?? null;
                if ($prediction === null) {
                    $points[] = 0.0;
                    $flat[] = 0;
                    $score[] = 0;
                    $winners[] = 0;
                    $lantern[] = 0;
                    $inconsistent[] = 0;
                    continue;
                }

                $ms = $this->scorePrediction($prediction);
                $points[] = $ms->total() * $weight;
                $flat[] = $ms->total();
                $score[] = $ms->homeGoalsPoint + $ms->awayGoalsPoint + $ms->exactBonusPoint;
                $winners[] = $ms->advancePoints > 0 ? 1 : 0;
                $lantern[] = $this->lanternPenalty($prediction);

                $inconsistent[] = $prediction->isInconsistent() ? 1 : 0;
            }

            $steps[] = [
                'label' => (string) $match,
                'round' => $match->getRound()?->getName(),
                'points' => $points,
                'pointsMax' => self::MAX_RAW_PER_MATCH * $weight,
                'flat' => $flat,
                'flatMax' => self::MAX_RAW_PER_MATCH,
                'score' => $score,
                'scoreMax' => self::POINTS_PER_GOAL_SIDE * 2 + self::EXACT_BONUS,
                'winners' => $winners,
                'winnersMax' => 1,
                'lantern' => $lantern,
                'lanternMax' => self::MAX_LANTERN_PER_MATCH,
                'inconsistent' => $inconsistent,
                'inconsistentMax' => 1,
            ];
        }

        $playerData = [];
        foreach ($players as $user) {
            $playerData[] = [
                'name' => $user->getDisplayName(),
                'slug' => $user->getSlug(),
                'avatar' => $user->getAvatar(),
            ];
        }

        return ['players' => $playerData, 'steps' => $steps];
    }

    /**
     * Per afgeronde wedstrijd, per (meegegeven) speler: de voorspelde uitslag en de
     * gescoorde punten. Bedoeld voor de API/MCP, zodat de gescoorde punten en de
     * voorspelling per speler per wedstrijd opgehaald kunnen worden zonder zelf na te
     * rekenen. Alleen afgeronde wedstrijden, dus voorspellingen lekken niet vóór de aftrap.
     *
     * @param list<int>|null $userIds
     *
     * @return list<array<string, mixed>>
     */
    public function matchBreakdown(?array $userIds = null): array
    {
        $rounds = $this->roundsById();
        $players = $this->participantPlayers($userIds);
        $byMatch = $this->predictionsByMatch();

        $matches = [];
        foreach ($this->finishedMatches() as $match) {
            $weight = $match->getRound() !== null
                ? (($rounds[$match->getRound()->getId()] ?? null)?->getWeight() ?? 1.0)
                : 1.0;

            $predictions = [];
            foreach ($players as $uid => $user) {
                $prediction = $byMatch[$match->getId()][$uid] ?? null;
                $score = $prediction !== null ? $this->scorePrediction($prediction) : null;

                $predictions[] = [
                    'player' => $user->getDisplayName(),
                    'slug' => $user->getSlug(),
                    'homeScore' => $prediction?->getHomeScore(),
                    'awayScore' => $prediction?->getAwayScore(),
                    'advancingSide' => $prediction?->getAdvancingSide(),
                    'points' => $score !== null ? $score->total() * $weight : 0.0,
                    'rawPoints' => $score?->total() ?? 0,
                    'scorePoints' => $score !== null
                        ? $score->homeGoalsPoint + $score->awayGoalsPoint + $score->exactBonusPoint
                        : 0,
                    'winner' => $score !== null && $score->advancePoints > 0,
                ];
            }

            $matches[] = [
                'matchId' => $match->getId(),
                'round' => $match->getRound()?->getName(),
                'weight' => $weight,
                'home' => $match->getHomeTeam(),
                'away' => $match->getAwayTeam(),
                'homeScore' => $match->getHomeScore(),
                'awayScore' => $match->getAwayScore(),
                'advancingSide' => $match->getAdvancingSide(),
                'advancingTeam' => $match->getAdvancingTeam(),
                'predictions' => $predictions,
            ];
        }

        return $matches;
    }

    /**
     * Begin van de laatste speeldag: het referentiepunt voor de movement-pijlen.
     * Niet middernacht, maar het configureerbare grensuur (NL wandtijd) dat een
     * speeldag afbakent — standaard 09:00. Het toernooi speelt in de VS, dus een
     * speeldag loopt in NL-tijd over middernacht heen (avond → vroege ochtend);
     * rond 09:00 wordt geen wedstrijd meer gespeeld. Zo blijven wedstrijden van
     * dezelfde speeldag samen i.p.v. dat een middernacht-grens ze uit elkaar trekt.
     */
    public function lastMatchdayStart(): ?\DateTimeImmutable
    {
        $latest = $this->finishedSummary()['latest'];
        if ($latest === null) {
            return null;
        }

        $boundary = $latest->setTime($this->matchdayBoundaryHour, 0, 0);
        if ($boundary > $latest) {
            $boundary = $boundary->modify('-1 day');
        }

        return $boundary;
    }

    /**
     * @return array<int, int>
     */
    public function finishedCountPerRound(): array
    {
        return $this->finishedSummary()['count'];
    }

    /**
     * @return array<int, Round>
     */
    private function roundsById(): array
    {
        if ($this->roundsByIdCache !== null) {
            return $this->roundsByIdCache;
        }

        $rounds = [];
        foreach ($this->roundRepository->findAllBySortOrder() as $round) {
            $rounds[$round->getId()] = $round;
        }

        return $this->roundsByIdCache = $rounds;
    }

    /**
     * @return list<FootballMatch>
     */
    private function allMatches(): array
    {
        if ($this->allMatchesCache !== null) {
            return $this->allMatchesCache;
        }

        return $this->allMatchesCache = $this->footballMatchRepository->findAllForOverview();
    }

    /**
     * @return list<FootballMatch>
     */
    private function finishedMatches(): array
    {
        if ($this->finishedMatchesCache !== null) {
            return $this->finishedMatchesCache;
        }

        // Alleen wedstrijden met een echte uitslag (afgerond én beide scores) tellen
        // als "gespeeld": zo lopen het maximum, de speeldag-referentie en de scoring
        // niet uiteen bij een (theoretisch) afgeronde wedstrijd zonder scores.
        return $this->finishedMatchesCache = array_values(array_filter(
            $this->allMatches(),
            static fn (FootballMatch $match): bool => $match->hasResult(),
        ));
    }

    /**
     * @return list<Prediction>
     */
    private function scoringPredictions(): array
    {
        if ($this->scoringPredictionsCache !== null) {
            return $this->scoringPredictionsCache;
        }

        return $this->scoringPredictionsCache = $this->predictionRepository->findAllForScoring();
    }

    /**
     * @return array<int, true>
     */
    private function participantIds(): array
    {
        if ($this->participantIdsCache !== null) {
            return $this->participantIdsCache;
        }

        return $this->participantIdsCache = array_fill_keys($this->predictionRepository->userIdsWithPredictions(), true);
    }

    /**
     * @return list<User>
     */
    private function users(): array
    {
        if ($this->usersCache !== null) {
            return $this->usersCache;
        }

        return $this->usersCache = $this->userRepository->findAll();
    }

    /**
     * De deelnemende spelers (optioneel beperkt tot $userIds), gekeyd op gebruikers-id.
     *
     * @param list<int>|null $userIds
     *
     * @return array<int, User>
     */
    private function participantPlayers(?array $userIds): array
    {
        $participantIds = $this->participantIds();
        $allowed = $userIds === null ? null : array_flip($userIds);

        $players = [];
        foreach ($this->users() as $user) {
            if (!isset($participantIds[$user->getId()])) {
                continue;
            }
            if ($allowed !== null && !isset($allowed[$user->getId()])) {
                continue;
            }
            $players[$user->getId()] = $user;
        }

        return $players;
    }

    /**
     * De te scoren voorspellingen, geïndexeerd op wedstrijd-id en daarbinnen op speler-id.
     *
     * @return array<int, array<int, Prediction>>
     */
    private function predictionsByMatch(): array
    {
        $byMatch = [];
        foreach ($this->scoringPredictions() as $prediction) {
            $byMatch[$prediction->getFootballMatch()->getId()][$prediction->getUser()->getId()] = $prediction;
        }

        return $byMatch;
    }

    /**
     * @return array{count: array<int, int>, max: float, latest: ?\DateTimeImmutable}
     */
    private function finishedSummary(): array
    {
        if ($this->finishedSummaryCache !== null) {
            return $this->finishedSummaryCache;
        }

        $rounds = $this->roundsById();
        $counts = [];
        $max = 0.0;
        $latest = null;

        foreach ($this->finishedMatches() as $match) {
            $kickoff = $match->getKickoffAt();
            if ($kickoff !== null && ($latest === null || $kickoff > $latest)) {
                $latest = $kickoff;
            }

            if ($match->getRound() === null) {
                continue;
            }

            $roundId = $match->getRound()->getId();
            $counts[$roundId] = ($counts[$roundId] ?? 0) + 1;
            $max += self::MAX_RAW_PER_MATCH * (($rounds[$roundId] ?? null)?->getWeight() ?? 1.0);
        }

        return $this->finishedSummaryCache = [
            'count' => $counts,
            'max' => $max,
            'latest' => $latest,
        ];
    }

    /**
     * @return array{count: int, max: float}
     */
    private function tournamentSummary(): array
    {
        if ($this->tournamentSummaryCache !== null) {
            return $this->tournamentSummaryCache;
        }

        $rounds = $this->roundsById();
        $count = 0;
        $max = 0.0;

        foreach ($this->allMatches() as $match) {
            ++$count;
            if ($match->getRound() === null) {
                continue;
            }

            $roundId = $match->getRound()->getId();
            $max += self::MAX_RAW_PER_MATCH * (($rounds[$roundId] ?? null)?->getWeight() ?? 1.0);
        }

        return $this->tournamentSummaryCache = [
            'count' => $count,
            'max' => $max,
        ];
    }

    /**
     * @param list<int>|null $userIds
     */
    private function leaderboardCacheKey(?\DateTimeImmutable $before, ?array $userIds): string
    {
        if ($userIds !== null) {
            sort($userIds);
        }

        return ($before?->format(\DateTimeInterface::ATOM) ?? 'all') . '|' . json_encode($userIds);
    }

    private function hasFinishedMatchesBefore(\DateTimeImmutable $cutoff): bool
    {
        foreach ($this->finishedMatches() as $match) {
            $kickoff = $match->getKickoffAt();
            if ($kickoff !== null && $kickoff < $cutoff) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strafpunten voor de ronde lantaarn op één (gespeelde) voorspelling:
     *   +1 als een gelijkspel is voorspeld terwijl het géén gelijkspel werd;
     *   +1 als winst/verlies is voorspeld terwijl het een gelijkspel werd;
     *   +2 als de uitslag omgekeerd is voorspeld (verkeerde ploeg won);
     *   +1 als de verkeerde ploeg tot winnaar is uitgeroepen.
     * Zonder uitslag (nog niet gespeeld) levert dit nul punten op.
     */
    public function lanternPenalty(Prediction $prediction): int
    {
        $match = $prediction->getFootballMatch();
        if ($match === null || !$match->hasResult()) {
            return 0;
        }

        $penalty = 0;

        $ph = $prediction->getHomeScore();
        $pa = $prediction->getAwayScore();
        $ah = $match->getHomeScore();
        $aa = $match->getAwayScore();
        if ($ph !== null && $pa !== null && $ah !== null && $aa !== null) {
            $predDiff = $ph <=> $pa;
            $actualDiff = $ah <=> $aa;
            if ($predDiff === 0 && $actualDiff !== 0) {
                $penalty += 1;
            } elseif ($predDiff !== 0 && $actualDiff === 0) {
                $penalty += 1;
            } elseif ($predDiff !== 0 && $actualDiff !== 0 && $predDiff !== $actualDiff) {
                $penalty += 2;
            }
        }

        if ($match->getAdvancingSide() !== null
            && $prediction->getAdvancingSide() !== null
            && $prediction->getAdvancingSide() !== $match->getAdvancingSide()) {
            $penalty += 1;
        }

        return $penalty;
    }
}
