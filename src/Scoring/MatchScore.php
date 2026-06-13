<?php

namespace App\Scoring;

/**
 * Puntenverdeling van één voorspelling voor een afgeronde wedstrijd.
 */
final class MatchScore
{
    public function __construct(
        public readonly int $homeGoalsPoint,
        public readonly int $awayGoalsPoint,
        public readonly int $exactBonusPoint,
        public readonly int $advancePoints,
    ) {
    }

    public function total(): int
    {
        return $this->homeGoalsPoint + $this->awayGoalsPoint + $this->exactBonusPoint + $this->advancePoints;
    }
}
