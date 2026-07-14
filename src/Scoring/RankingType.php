<?php

namespace App\Scoring;

/**
 * De klassementen ("truien") als enige bron van hun presentatie en sortering:
 * emoji/label/veld, of een hogere positie ongunstig is (invertedMovement), de
 * waarde per speler en de sorteervolgorde. Gedeeld door de web-weergave
 * (LeaderboardController) en de API (ApiNormalizer), zodat die niet uiteenlopen.
 */
enum RankingType: string
{
    case Points = 'points';
    case Flat = 'flat';
    case Score = 'score';
    case Winners = 'winners';
    case Lantern = 'lantern';
    case Inconsistent = 'inconsistent';

    public function emoji(): string
    {
        return match ($this) {
            self::Points => '🟡',
            self::Flat => '🥞',
            self::Score => '⚽',
            self::Winners => '🔮',
            self::Lantern => '🔴',
            self::Inconsistent => '🤔',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Points => 'Algemeen',
            self::Flat => 'Plattement',
            self::Score => 'Balletjestrui',
            self::Winners => 'Glazen bal',
            self::Lantern => 'Ronde lantaarn',
            self::Inconsistent => 'Tegenstrijdig',
        };
    }

    /**
     * Het veld in de stand waarop dit klassement sorteert (de noemer in de weergave).
     */
    public function field(): string
    {
        return match ($this) {
            self::Points => 'weightedTotal',
            self::Flat => 'rawTotal',
            self::Score => 'scorePoints',
            self::Winners => 'advanceCount',
            self::Lantern => 'lanternPoints',
            self::Inconsistent => 'inconsistentCount',
        };
    }

    /**
     * True voor straf-klassementen waar een hogere positie ongunstig is: een positieve
     * movement (richting plek 1) is dan "slechter", dus de weergave keert de kleur om.
     */
    public function invertedMovement(): bool
    {
        return $this === self::Lantern;
    }

    public function valueFor(LeaderboardEntry $entry): int|float
    {
        return match ($this) {
            self::Points => $entry->weightedTotal,
            self::Flat => $entry->rawTotal,
            self::Score => $entry->scorePoints,
            self::Winners => $entry->advanceCount,
            self::Lantern => $entry->lanternPoints,
            self::Inconsistent => $entry->inconsistentCount,
        };
    }

    /**
     * Sorteert aflopend op de waarde, daarna oplopend op naam. Punten breekt een
     * gelijke gewogen stand eerst op de ruwe punten (zoals het algemeen klassement
     * op de site), zodat de API dezelfde volgorde aanhoudt.
     */
    public function compare(LeaderboardEntry $a, LeaderboardEntry $b): int
    {
        if ($this === self::Points) {
            return [$b->weightedTotal, $b->rawTotal, $a->user->getDisplayName()]
                <=> [$a->weightedTotal, $a->rawTotal, $b->user->getDisplayName()];
        }

        return [$this->valueFor($b), $a->user->getDisplayName()]
            <=> [$this->valueFor($a), $b->user->getDisplayName()];
    }
}
