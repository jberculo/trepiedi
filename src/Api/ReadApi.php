<?php

namespace App\Api;

use App\Entity\Pool;
use App\Entity\Round;
use App\Entity\User;
use App\Repository\FootballMatchRepository;
use App\Repository\PoolRepository;
use App\Repository\PredictionRepository;
use App\Repository\RoundRepository;
use App\Scoring\ScoringService;

/**
 * Lees-operaties van de API: stand, wedstrijden, ronden en het eigen profiel.
 */
class ReadApi
{
    use AuthorizesApiUser;

    public function __construct(
        private PoolRepository $pools,
        private FootballMatchRepository $matches,
        private PredictionRepository $predictions,
        private RoundRepository $rounds,
        private ScoringService $scoring,
        private ApiNormalizer $normalizer,
    ) {
    }

    public function standings(?string $code): array
    {
        $pool = ($code !== null && $code !== '') ? $this->pools->findOneActiveByCode($code) : $this->pools->findDefault();
        if ($pool === null) {
            throw new ApiException(ApiError::NotFound, 'Onbekende of gearchiveerde poule.');
        }

        $entries = $this->scoring->leaderboardWithMovement($pool->memberIds());

        return [
            'pool' => ['name' => $pool->getName(), 'code' => $pool->getCode()],
            'types' => $this->normalizer->rankingTypes(),
            'standings' => $this->normalizer->standings($entries),
        ];
    }

    public function timeline(?string $code): array
    {
        $pool = ($code !== null && $code !== '') ? $this->pools->findOneActiveByCode($code) : $this->pools->findDefault();
        if ($pool === null) {
            throw new ApiException(ApiError::NotFound, 'Onbekende of gearchiveerde poule.');
        }

        return [
            'pool' => ['name' => $pool->getName(), 'code' => $pool->getCode()],
            'matches' => $this->scoring->matchBreakdown($pool->memberIds()),
        ];
    }

    public function matchesList(): array
    {
        $matches = array_map($this->normalizer->match(...), $this->matches->findAllChronological());

        return ['matches' => $matches, 'flags' => $this->normalizer->flagSvgs($matches)];
    }

    public function matchDetail(int $id): array
    {
        $match = $this->matches->find($id);
        if ($match === null) {
            throw new ApiException(ApiError::NotFound, 'Wedstrijd niet gevonden.');
        }

        $data = $this->normalizer->match($match);
        $data['flags'] = $this->normalizer->flagSvgs([$data]);
        $data['predictionCount'] = $this->predictions->countByMatch($match);

        // Voorspellingen worden pas zichtbaar zodra de wedstrijd is begonnen.
        if ($match->isLocked()) {
            $data['predictions'] = array_map($this->normalizer->prediction(...), $this->predictions->findByMatch($match));
        }

        return $data;
    }

    public function roundsList(): array
    {
        $rounds = array_map(static fn (Round $r): array => [
            'name' => $r->getName(),
            'sortOrder' => $r->getSortOrder(),
            'weight' => $r->getWeight(),
            'matchCount' => count($r->getMatches()),
        ], $this->rounds->findAllBySortOrder());

        return ['rounds' => $rounds];
    }

    public function me(?User $user): array
    {
        $user = $this->requireUser($user);

        $pools = array_map(static fn (Pool $p): array => [
            'name' => $p->getName(),
            'code' => $p->getCode(),
            'default' => $p->isDefault(),
            'archived' => $p->isArchived(),
        ], $user->getPools()->toArray());

        return [
            'displayName' => $user->getDisplayName(),
            'slug' => $user->getSlug(),
            'admin' => $user->isAdmin(),
            'pools' => array_values($pools),
            'activePool' => $user->getActivePool()?->getCode(),
        ];
    }
}
