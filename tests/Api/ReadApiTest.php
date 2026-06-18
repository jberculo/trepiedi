<?php

namespace App\Tests\Api;

use App\Api\ApiError;
use App\Api\ApiException;
use App\Api\ApiNormalizer;
use App\Api\ReadApi;
use App\Entity\FootballMatch;
use App\Entity\Pool;
use App\Repository\FootballMatchRepository;
use App\Repository\PoolRepository;
use App\Repository\PredictionRepository;
use App\Repository\RoundRepository;
use App\Scoring\ScoringService;
use PHPUnit\Framework\TestCase;

class ReadApiTest extends TestCase
{
    public function testMatchDetailCountsPredictionsWithoutLoadingRowsWhenUnlocked(): void
    {
        $match = (new FootballMatch())
            ->setHomeTeam('Nederland')
            ->setAwayTeam('Duitsland')
            ->setKickoffAt(new \DateTimeImmutable('+1 day'))
            ->setActive(true);

        $matches = $this->createMock(FootballMatchRepository::class);
        $predictions = $this->createMock(PredictionRepository::class);
        $pools = $this->createStub(PoolRepository::class);
        $rounds = $this->createStub(RoundRepository::class);
        $scoring = $this->createStub(ScoringService::class);
        $normalizer = $this->createMock(ApiNormalizer::class);

        $matches->expects($this->once())->method('find')->with(12)->willReturn($match);
        $predictions->expects($this->once())->method('countByMatch')->with($match)->willReturn(7);
        $predictions->expects($this->never())->method('findByMatch');
        $normalizer->expects($this->once())->method('match')->with($match)->willReturn(['id' => 12]);
        $normalizer->expects($this->once())->method('flagSvgs')->with([['id' => 12]])->willReturn(['nl' => '<svg />']);

        $data = (new ReadApi($pools, $matches, $predictions, $rounds, $scoring, $normalizer))->matchDetail(12);

        $this->assertSame(7, $data['predictionCount']);
        $this->assertArrayNotHasKey('predictions', $data);
    }

    public function testStandingsThrowsNotFoundForUnknownPool(): void
    {
        $pools = $this->createMock(PoolRepository::class);
        $matches = $this->createStub(FootballMatchRepository::class);
        $predictions = $this->createStub(PredictionRepository::class);
        $rounds = $this->createStub(RoundRepository::class);
        $scoring = $this->createStub(ScoringService::class);
        $normalizer = $this->createStub(ApiNormalizer::class);

        $pools->expects($this->once())->method('findOneActiveByCode')->with('bestaat-niet')->willReturn(null);

        try {
            (new ReadApi($pools, $matches, $predictions, $rounds, $scoring, $normalizer))->standings('bestaat-niet');
            $this->fail('Expected ApiException was not thrown.');
        } catch (ApiException $e) {
            $this->assertSame(ApiError::NotFound, $e->error);
        }
    }
}
