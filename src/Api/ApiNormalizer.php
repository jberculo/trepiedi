<?php

namespace App\Api;

use App\Entity\FootballMatch;
use App\Entity\Prediction;
use App\Flag\FlagProvider;
use App\Reference\Countries;
use App\Scoring\LeaderboardEntry;

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
        foreach ($this->rankingDefinitions() as $def) {
            $key = $def['key'];
            $value = $def['value'];

            $sorted = $entries;
            usort($sorted, static fn (LeaderboardEntry $a, LeaderboardEntry $b): int =>
                [$value($b), $a->user->getDisplayName()] <=> [$value($a), $b->user->getDisplayName()]);

            $rows = [];
            $rank = 0;
            $prev = null;
            foreach ($sorted as $i => $e) {
                $v = $value($e);
                if ($prev === null || abs((float) $v - (float) $prev) > 0.0001) {
                    $rank = $i + 1;
                    $prev = $v;
                }

                $rows[] = [
                    'rank' => $rank,
                    'movement' => $e->rankChange[$key] ?? null,
                    'player' => $e->user->getDisplayName(),
                    'slug' => $e->user->getSlug(),
                    'value' => $v,
                ];
            }

            $rankings[$key] = ['field' => $def['field'], 'entries' => $rows];
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
     * De klassement-types met hun emoji en het bijbehorende veld in de stand
     * (presentatie-metadata bij `rankings`).
     *
     * @return list<array{key: string, emoji: string, label: string, field: string}>
     */
    public function rankingTypes(): array
    {
        return array_map(
            static fn (array $def): array => [
                'key' => $def['key'],
                'emoji' => $def['emoji'],
                'label' => $def['label'],
                'field' => $def['field'],
            ],
            $this->rankingDefinitions(),
        );
    }

    /**
     * De enige bron van de klassementen: presentatie-metadata plus hoe je de waarde
     * van dat klassement uit een stand-entry haalt. Gedeeld door `rankingTypes()`
     * (metadata) en `rankings()` (sorteren + waarde per speler).
     *
     * @return list<array{key: string, emoji: string, label: string, field: string, value: callable(LeaderboardEntry): (int|float)}>
     */
    private function rankingDefinitions(): array
    {
        return [
            ['key' => 'points', 'emoji' => '🟡', 'label' => 'Algemeen', 'field' => 'weightedTotal', 'value' => static fn (LeaderboardEntry $e): float => $e->weightedTotal],
            ['key' => 'score', 'emoji' => '⚽', 'label' => 'Balletjestrui', 'field' => 'scorePoints', 'value' => static fn (LeaderboardEntry $e): int => $e->scorePoints],
            ['key' => 'winners', 'emoji' => '🔮', 'label' => 'Glazen bal', 'field' => 'winners', 'value' => static fn (LeaderboardEntry $e): int => $e->advanceCount],
            ['key' => 'lantern', 'emoji' => '🔴', 'label' => 'Ronde lantaarn', 'field' => 'lanternPoints', 'value' => static fn (LeaderboardEntry $e): int => $e->lanternPoints],
            ['key' => 'inconsistent', 'emoji' => '🤔', 'label' => 'Tegenstrijdig', 'field' => 'inconsistent', 'value' => static fn (LeaderboardEntry $e): int => $e->inconsistentCount],
        ];
    }
}
