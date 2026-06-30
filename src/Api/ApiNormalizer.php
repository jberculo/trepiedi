<?php

namespace App\Api;

use App\Entity\FootballMatch;
use App\Entity\Prediction;
use App\Flag\FlagProvider;
use App\Reference\Countries;
use App\Scoring\LeaderboardEntry;
use App\Scoring\Ranker;
use App\Scoring\RankingType;

/**
 * Zet domeinobjecten om naar de API-data-arrays (presentatie), zodat de
 * read/write-services en de MCP-laag dezelfde representatie delen.
 */
class ApiNormalizer
{
    public function __construct(private FlagProvider $flags)
    {
    }

    /**
     * De stand per klassement: voor elk type een eigen, al gesorteerde lijst met per
     * speler de rang, de waarde van dat klassement en de positieverandering t.o.v. de
     * vorige speeldag (`movement`, null = geen vergelijking). De rang is tie-aware
     * (gelijke waarde => zelfde rang), net als op de site.
     *
     * @param list<LeaderboardEntry> $entries
     *
     * @return array<string, array{field: string, entries: list<array<string, mixed>>}>
     */
    public function rankings(array $entries): array
    {
        $rankings = [];
        foreach (RankingType::cases() as $type) {
            $sorted = $entries;
            usort($sorted, $type->compare(...));
            $ranks = Ranker::assign($sorted, $type->valueFor(...));

            $rows = [];
            foreach ($sorted as $i => $e) {
                $rows[] = [
                    'rank' => $ranks[$i],
                    'movement' => $e->rankChange[$type->value] ?? null,
                    'player' => $e->user->getDisplayName(),
                    'slug' => $e->user->getSlug(),
                    'value' => $type->valueFor($e),
                ];
            }

            $rankings[$type->value] = ['field' => $type->field(), 'entries' => $rows];
        }

        return $rankings;
    }

    /**
     * @return array<string, mixed>
     */
    public function match(FootballMatch $match): array
    {
        return [
            'id' => $match->getId(),
            'round' => $match->getRound()?->getName(),
            'kickoff' => $match->getKickoffAt()?->format(\DateTimeInterface::ATOM),
            'home' => $match->getHomeTeam(),
            'away' => $match->getAwayTeam(),
            'homeFlag' => Countries::codeForName($match->getHomeTeam()),
            'awayFlag' => Countries::codeForName($match->getAwayTeam()),
            'homeScore' => $match->getHomeScore(),
            'awayScore' => $match->getAwayScore(),
            'advancingTeam' => $match->getAdvancingTeam(),
            'advancingFlag' => Countries::codeForName($match->getAdvancingTeam()),
            'advancingSide' => $match->getAdvancingSide(),
            'finished' => $match->isFinished(),
            'open' => !$match->isFinished(),
            'resultViaExternalApi' => $match->isResultViaExternalApi(),
            'active' => $match->isActive(),
            'locked' => $match->isLocked(),
            'predictable' => $match->isActive() && !$match->isLocked(),
        ];
    }

    public function prediction(Prediction $prediction): array
    {
        return [
            'player' => $prediction->getUser()->getDisplayName(),
            'homeScore' => $prediction->getHomeScore(),
            'awayScore' => $prediction->getAwayScore(),
            'advancingSide' => $prediction->getAdvancingSide(),
        ];
    }

    /**
     * Gededupliceerde map flag-code -> SVG voor de gegeven wedstrijd-arrays, zodat
     * de client de vlaggetjes zelf kan renderen.
     *
     * @param list<array<string, mixed>> $matches
     *
     * @return array<string, string>
     */
    public function flagSvgs(array $matches): array
    {
        $map = [];
        foreach ($matches as $match) {
            foreach (['homeFlag', 'awayFlag', 'advancingFlag'] as $key) {
                $code = $match[$key] ?? null;
                if (is_string($code) && !isset($map[$code]) && ($svg = $this->flags->svg($code)) !== null) {
                    $map[$code] = $svg;
                }
            }
        }

        return $map;
    }

    /**
     * De klassement-types met hun emoji, label, bijbehorende veld en of een hogere
     * positie ongunstig is (`invertedMovement`). Afgeleid uit `RankingType`, de enige
     * bron die ook de web-weergave voedt.
     *
     * @return list<array{key: string, emoji: string, label: string, field: string, invertedMovement: bool}>
     */
    public function rankingTypes(): array
    {
        return array_map(
            static fn (RankingType $type): array => [
                'key' => $type->value,
                'emoji' => $type->emoji(),
                'label' => $type->label(),
                'field' => $type->field(),
                'invertedMovement' => $type->invertedMovement(),
            ],
            RankingType::cases(),
        );
    }
}
