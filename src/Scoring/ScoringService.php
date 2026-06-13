<?php

namespace App\Scoring;

use App\Entity\FootballMatch;
use App\Entity\Prediction;
use App\Entity\Round;
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
 * Elke wedstrijd telt standaard even zwaar (maximaal 6 punten). Het rondegewicht
 * is de expliciete vermenigvuldiger: de gewogen score van een wedstrijd is
 * "punten × rondegewicht". Met gewicht 1 is de gewogen score dus gelijk aan de
 * ruwe punten.
 */
class ScoringService
{
    public const POINTS_PER_GOAL_SIDE = 1;
    public const EXACT_BONUS = 1;
    public const ADVANCE_POINTS = 3;

    /** Maximaal haalbare ruwe punten per wedstrijd: 1 + 1 + 1 + 3 = 6. */
    public const MAX_RAW_PER_MATCH = self::POINTS_PER_GOAL_SIDE * 2 + self::EXACT_BONUS + self::ADVANCE_POINTS;

    /** Maximale strafpunten (ronde lantaarn) per wedstrijd: 2 omgekeerd + 1 verkeerde winnaar. */
    public const MAX_LANTERN_PER_MATCH = 3;

    public function __construct(
        private PredictionRepository $predictionRepository,
        private FootballMatchRepository $footballMatchRepository,
        private RoundRepository $roundRepository,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * Punten van één voorspelling. Levert nul punten als de wedstrijd nog geen uitslag heeft.
     */
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

    /**
     * Maximaal haalbare gewogen score over alle reeds gespeelde wedstrijden:
     * per gespeelde wedstrijd 6 punten × het rondegewicht.
     */
    public function maxAchievableTotal(): float
    {
        $rounds = $this->roundsById();
        $max = 0.0;
        foreach ($this->finishedCountPerRound() as $roundId => $count) {
            $weight = isset($rounds[$roundId]) ? $rounds[$roundId]->getWeight() : 1.0;
            $max += $count * self::MAX_RAW_PER_MATCH * $weight;
        }

        return $max;
    }

    /**
     * Maximaal haalbare gewogen score over het hele toernooi (alle wedstrijden,
     * inclusief open en inactieve).
     */
    public function maxTournamentTotal(): float
    {
        $rounds = $this->roundsById();
        $max = 0.0;
        foreach ($this->footballMatchRepository->findAll() as $match) {
            if ($match->getRound() === null) {
                continue;
            }
            $weight = isset($rounds[$match->getRound()->getId()])
                ? $rounds[$match->getRound()->getId()]->getWeight()
                : 1.0;
            $max += self::MAX_RAW_PER_MATCH * $weight;
        }

        return $max;
    }

    /**
     * Aantal wedstrijden in het hele toernooi (alle, inclusief inactieve).
     */
    public function tournamentMatchCount(): int
    {
        return count($this->footballMatchRepository->findAll());
    }

    /**
     * Bouwt het volledige klassement, gesorteerd op gewogen totaal (aflopend).
     *
     * @return list<LeaderboardEntry>
     */
    public function buildLeaderboard(?\DateTimeImmutable $before = null): array
    {
        $rounds = $this->roundsById();

        // Alleen spelers die daadwerkelijk hebben meegedaan (≥ 1 voorspelling).
        $participantIds = array_flip($this->predictionRepository->userIdsWithPredictions());

        /** @var array<int, LeaderboardEntry> $entries */
        $entries = [];
        foreach ($this->userRepository->findAll() as $user) {
            if (!isset($participantIds[$user->getId()])) {
                continue;
            }
            $entries[$user->getId()] = new LeaderboardEntry($user);
        }

        // Punten verzamelen: per wedstrijd "ruwe punten × rondegewicht".
        foreach ($this->predictionRepository->findAllForScoring() as $prediction) {
            $user = $prediction->getUser();
            $match = $prediction->getFootballMatch();
            if ($user === null || $match === null || $match->getRound() === null) {
                continue;
            }

            $entry = $entries[$user->getId()] ?? null;
            if ($entry === null) {
                continue;
            }

            // Voor een historische stand: wedstrijden vanaf de cutoff niet meetellen.
            if ($before !== null && ($match->getKickoffAt() === null || $match->getKickoffAt() >= $before)) {
                continue;
            }

            $roundId = $match->getRound()->getId();
            $weight = isset($rounds[$roundId]) ? $rounds[$roundId]->getWeight() : 1.0;
            $score = $this->scorePrediction($prediction);
            $points = $score->total();
            $weighted = $points * $weight;

            $entry->rounds[$roundId]['raw'] = ($entry->rounds[$roundId]['raw'] ?? 0) + $points;
            $entry->rounds[$roundId]['weighted'] = ($entry->rounds[$roundId]['weighted'] ?? 0.0) + $weighted;
            $entry->rawTotal += $points;
            $entry->weightedTotal += $weighted;

            // Score-onderdelen (thuis, uit, exacte eindstand) los bijhouden, ook per ronde.
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

            // Tegenstrijdige voorspelling: de voorspelde uitslag wijst een kant als winnaar aan,
            // maar de speler laat de andere kant doorgaan.
            $scoreWinner = $this->predictedWinnerSide($prediction);
            if ($match->hasResult()
                && $scoreWinner !== null
                && $prediction->getAdvancingSide() !== null
                && $prediction->getAdvancingSide() !== $scoreWinner) {
                ++$entry->inconsistentCount;
                $entry->rounds[$roundId]['inconsistent'] = ($entry->rounds[$roundId]['inconsistent'] ?? 0) + 1;
            }

            // Ronde lantaarn: strafpunten voor een slechte voorspelling op een
            // gespeelde wedstrijd. Niet-ingevulde wedstrijden tellen niet mee.
            $penalty = $this->lanternPenalty($prediction);
            if ($penalty > 0) {
                $entry->lanternPoints += $penalty;
                $entry->rounds[$roundId]['lantern'] = ($entry->rounds[$roundId]['lantern'] ?? 0) + $penalty;
            }
        }

        $list = array_values($entries);
        usort($list, function (LeaderboardEntry $a, LeaderboardEntry $b): int {
            return [$b->weightedTotal, $b->rawTotal, $a->user->getDisplayName()]
                <=> [$a->weightedTotal, $a->rawTotal, $b->user->getDisplayName()];
        });

        $rank = 0;
        $prevTotal = null;
        foreach ($list as $i => $entry) {
            if ($prevTotal === null || abs($entry->weightedTotal - $prevTotal) > 0.0001) {
                $rank = $i + 1;
                $prevTotal = $entry->weightedTotal;
            }
            $entry->rank = $rank;
        }

        return $list;
    }

    /**
     * Het algemeen klassement met de positieverandering sinds de vorige speeldag
     * (de laatste dag waarop wedstrijden zijn gespeeld) ingevuld per speler.
     *
     * @return list<LeaderboardEntry>
     */
    public function leaderboardWithMovement(): array
    {
        $current = $this->buildLeaderboard();

        $cutoff = $this->lastMatchdayStart();
        if ($cutoff === null) {
            return $current;
        }

        $previous = $this->buildLeaderboard($cutoff);

        $hasHistory = false;
        foreach ($previous as $entry) {
            if ($entry->rawTotal > 0) {
                $hasHistory = true;
                break;
            }
        }

        // Eerste speeldag: geen vorige stand om mee te vergelijken.
        if (!$hasHistory) {
            return $current;
        }

        // Voor elk klassement de positieverandering bepalen (sorteersleutel per ranking).
        $rankings = [
            'points' => static fn (LeaderboardEntry $e): array => [$e->weightedTotal, $e->rawTotal],
            'score' => static fn (LeaderboardEntry $e): array => [$e->scorePoints, $e->weightedTotal],
            'winners' => static fn (LeaderboardEntry $e): array => [$e->advanceCount, $e->weightedTotal],
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
     * Strikte (unieke) lijstpositie op basis van een sorteersleutel (aflopend),
     * met de weergavenaam als beslisser. Strikte posities zorgen dat de
     * positieverandering behoudend is: elke daler heeft een stijger.
     *
     * @param list<LeaderboardEntry>                       $entries
     * @param callable(LeaderboardEntry): list<int|float>  $metric
     *
     * @return array<int, int> gebruiker-id => positie (1-gebaseerd)
     */
    private function positionMap(array $entries, callable $metric): array
    {
        $list = $entries;
        usort($list, static function (LeaderboardEntry $a, LeaderboardEntry $b) use ($metric): int {
            return [...$metric($b), $a->user->getDisplayName()] <=> [...$metric($a), $b->user->getDisplayName()];
        });

        $map = [];
        foreach ($list as $i => $entry) {
            $map[$entry->user->getId()] = $i + 1;
        }

        return $map;
    }

    /**
     * Tijdlijn voor de animatie: per afgeronde wedstrijd (chronologisch) de punten
     * die elke speler behaalde, voor elk klassement (algemeen/score/winnaars),
     * plus het maximaal haalbare per wedstrijd per klassement.
     *
     * @return array{players: list<array{name: string, slug: ?string}>, steps: list<array<string, mixed>>}
     */
    public function matchTimeline(): array
    {
        $rounds = $this->roundsById();
        $participantIds = array_flip($this->predictionRepository->userIdsWithPredictions());

        $players = [];
        foreach ($this->userRepository->findAll() as $user) {
            if (isset($participantIds[$user->getId()])) {
                $players[$user->getId()] = $user;
            }
        }

        // Voorspellingen indexeren op wedstrijd-id en gebruiker-id.
        $byMatch = [];
        foreach ($this->predictionRepository->findAllForScoring() as $prediction) {
            $byMatch[$prediction->getFootballMatch()->getId()][$prediction->getUser()->getId()] = $prediction;
        }

        $playerIds = array_keys($players);
        $steps = [];
        foreach ($this->footballMatchRepository->findBy(['finished' => true], ['kickoffAt' => 'ASC']) as $match) {
            $weight = $match->getRound() !== null && isset($rounds[$match->getRound()->getId()])
                ? $rounds[$match->getRound()->getId()]->getWeight()
                : 1.0;

            $points = [];
            $score = [];
            $winners = [];
            $lantern = [];
            $inconsistent = [];
            foreach ($playerIds as $uid) {
                $prediction = $byMatch[$match->getId()][$uid] ?? null;
                if ($prediction === null) {
                    $points[] = 0.0;
                    $score[] = 0;
                    $winners[] = 0;
                    $lantern[] = 0;
                    $inconsistent[] = 0;
                    continue;
                }
                $ms = $this->scorePrediction($prediction);
                $points[] = $ms->total() * $weight;
                $score[] = $ms->homeGoalsPoint + $ms->awayGoalsPoint + $ms->exactBonusPoint;
                $winners[] = $ms->advancePoints > 0 ? 1 : 0;
                $lantern[] = $this->lanternPenalty($prediction);

                $scoreWinner = $this->predictedWinnerSide($prediction);
                $inconsistent[] = ($scoreWinner !== null
                    && $prediction->getAdvancingSide() !== null
                    && $prediction->getAdvancingSide() !== $scoreWinner) ? 1 : 0;
            }

            $steps[] = [
                'label' => (string) $match,
                'round' => $match->getRound()?->getName(),
                'points' => $points,
                'pointsMax' => self::MAX_RAW_PER_MATCH * $weight,
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
     * Begin (00:00) van de laatste dag waarop een afgeronde wedstrijd is gespeeld.
     */
    public function lastMatchdayStart(): ?\DateTimeImmutable
    {
        $latest = null;
        foreach ($this->footballMatchRepository->findBy(['finished' => true]) as $match) {
            $kickoff = $match->getKickoffAt();
            if ($kickoff !== null && ($latest === null || $kickoff > $latest)) {
                $latest = $kickoff;
            }
        }

        return $latest?->setTime(0, 0, 0);
    }

    /**
     * @return array<int, int> aantal afgeronde wedstrijden per ronde-id
     */
    public function finishedCountPerRound(): array
    {
        $counts = [];
        foreach ($this->footballMatchRepository->findBy(['finished' => true]) as $match) {
            if ($match->getRound() !== null) {
                $id = $match->getRound()->getId();
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @return array<int, Round>
     */
    private function roundsById(): array
    {
        $rounds = [];
        foreach ($this->roundRepository->findAll() as $round) {
            $rounds[$round->getId()] = $round;
        }

        return $rounds;
    }

    /**
     * De kant ('home'/'away') die volgens de voorspelde uitslag wint (méér doelpunten).
     * Geeft null bij een voorspeld gelijkspel of een onvolledige voorspelling.
     */
    private function predictedWinnerSide(Prediction $prediction): ?string
    {
        $home = $prediction->getHomeScore();
        $away = $prediction->getAwayScore();
        if ($home === null || $away === null || $home === $away) {
            return null;
        }

        return $home > $away ? FootballMatch::SIDE_HOME : FootballMatch::SIDE_AWAY;
    }

    /**
     * Strafpunten voor de ronde lantaarn op één (gespeelde) voorspelling:
     *   +1 als een gelijkspel is voorspeld terwijl het géén gelijkspel werd;
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
