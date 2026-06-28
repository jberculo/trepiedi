<?php

namespace App\Util;

use App\Entity\FootballMatch;

/**
 * Gedeelde regels over een uitslag/voorspelling: wie wint er op doelpunten en
 * is dat tegenstrijdig met de gekozen doorgaande ploeg. Eén bron van waarheid
 * voor zowel een ingevoerde uitslag (FootballMatch) als een voorspelling
 * (Prediction) en de scoreberekening.
 */
final class MatchOutcome
{
    /**
     * De winnende kant op basis van de doelpunten: 'home', 'away', of null bij
     * een gelijkspel of onvolledige score.
     */
    public static function scoreWinner(?int $homeScore, ?int $awayScore): ?string
    {
        if ($homeScore === null || $awayScore === null || $homeScore === $awayScore) {
            return null;
        }

        return $homeScore > $awayScore ? FootballMatch::SIDE_HOME : FootballMatch::SIDE_AWAY;
    }

    /**
     * Tegenstrijdig: er is een duidelijke score-winnaar, maar de doorgaande
     * kant is juist de andere ploeg. Een gelijkspel is nooit tegenstrijdig
     * (penalty's bepalen dan de winnaar).
     */
    public static function isInconsistent(?int $homeScore, ?int $awayScore, ?string $advancingSide): bool
    {
        $winner = self::scoreWinner($homeScore, $awayScore);

        return $winner !== null && $advancingSide !== null && $winner !== $advancingSide;
    }
}
