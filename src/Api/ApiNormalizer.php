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
     * @param list<LeaderboardEntry> $entries
     *
     * @return list<array<string, mixed>>
     */
    public function standings(array $entries): array
    {
        return array_map(static fn (LeaderboardEntry $e): array => [
            'rank' => $e->rank,
            'movement' => $e->rankChange['points'] ?? null,
            'player' => $e->user->getDisplayName(),
            'slug' => $e->user->getSlug(),
            'weightedTotal' => $e->weightedTotal,
            'rawTotal' => $e->rawTotal,
            'scorePoints' => $e->scorePoints,
            'winners' => $e->advanceCount,
            'lanternPoints' => $e->lanternPoints,
            'inconsistent' => $e->inconsistentCount,
        ], $entries);
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
     * De klassement-types met hun emoji en het bijbehorende veld in de stand;
     * de enige bron hiervan voor de API.
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
}
