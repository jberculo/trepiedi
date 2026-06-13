<?php

namespace App\Scoring;

use App\Entity\User;

/**
 * Eén regel in het klassement: het totaal en de opbouw per ronde voor één speler.
 */
final class LeaderboardEntry
{
    public float $weightedTotal = 0.0;
    public int $rawTotal = 0;
    public int $rank = 0;

    /**
     * Positieverandering sinds de vorige speeldag per klassement
     * (key 'points'|'score'|'winners' => +gestegen / −gedaald / null = geen historie).
     *
     * @var array<string, int>
     */
    public array $rankChange = [];

    /** Aantal exact goed voorspelde uitslagen (beide doelpunten goed). */
    public int $exactCount = 0;

    /** Aantal keer het juiste aantal thuisdoelpunten voorspeld. */
    public int $homeCorrect = 0;

    /** Aantal keer het juiste aantal uitdoelpunten voorspeld. */
    public int $awayCorrect = 0;

    /**
     * Score-onderdelen goed: 1 punt per juist voorspeld thuisdoelpunt-aantal,
     * uitdoelpunt-aantal en exacte eindstand (max 3 per wedstrijd).
     */
    public int $scorePoints = 0;

    /** Aantal keer de winnaar goed voorspeld. */
    public int $advanceCount = 0;

    /**
     * Strafpunten voor de ronde lantaarn (hoe slechter voorspeld, hoe hoger):
     * +1 gelijkspel voorspeld dat geen gelijkspel werd, +2 omgekeerde uitslag,
     * +1 de verkeerde ploeg tot winnaar uitgeroepen.
     */
    public int $lanternPoints = 0;

    /**
     * Aantal tegenstrijdige voorspellingen: de voorspelde uitslag wijst de ene ploeg
     * als winnaar aan, maar de speler laat de andere ploeg doorgaan.
     */
    public int $inconsistentCount = 0;

    /**
     * Per ronde-id: ruwe punten, gewogen score en het aantal exacte/winnaar-treffers.
     *
     * @var array<int, array{raw: int, weighted: float, exact?: int, advance?: int}>
     */
    public array $rounds = [];

    public function __construct(public readonly User $user)
    {
    }

    public function roundRaw(int $roundId): int
    {
        return $this->rounds[$roundId]['raw'] ?? 0;
    }

    public function roundWeighted(int $roundId): float
    {
        return $this->rounds[$roundId]['weighted'] ?? 0.0;
    }

    public function change(string $ranking): ?int
    {
        return $this->rankChange[$ranking] ?? null;
    }

    public function roundExact(int $roundId): int
    {
        return $this->rounds[$roundId]['exact'] ?? 0;
    }

    public function roundAdvance(int $roundId): int
    {
        return $this->rounds[$roundId]['advance'] ?? 0;
    }

    public function roundHome(int $roundId): int
    {
        return $this->rounds[$roundId]['home'] ?? 0;
    }

    public function roundAway(int $roundId): int
    {
        return $this->rounds[$roundId]['away'] ?? 0;
    }

    public function roundLantern(int $roundId): int
    {
        return $this->rounds[$roundId]['lantern'] ?? 0;
    }

    public function roundInconsistent(int $roundId): int
    {
        return $this->rounds[$roundId]['inconsistent'] ?? 0;
    }
}
