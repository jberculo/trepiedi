<?php

namespace App\Round;

use App\Entity\FootballMatch;
use App\Entity\Round;
use App\Repository\RoundRepository;

final class RoundViewProjector
{
    public function __construct(private RoundRepository $roundRepository)
    {
    }

    /**
     * @param callable(FootballMatch, Round): array<string, mixed> $projectMatch
     * @return list<array{round: Round, items: list<array<string, mixed>>}>
     */
    public function project(callable $projectMatch): array
    {
        $rounds = [];

        foreach ($this->roundRepository->findAllForRoundViews() as $round) {
            $items = [];
            foreach ($round->getMatches() as $match) {
                $items[] = $projectMatch($match, $round);
            }

            $rounds[] = [
                'round' => $round,
                'items' => $items,
            ];
        }

        return $rounds;
    }
}
