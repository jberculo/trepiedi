<?php

namespace App\Tests\Scoring;

use App\Scoring\Ranker;
use PHPUnit\Framework\TestCase;

class RankerTest extends TestCase
{
    public function testAssignsCompetitionRanksWithSharedRankForTies(): void
    {
        // Al aflopend gesorteerd; gelijke waarde deelt een rang, daarna springt de rang door.
        $ranks = Ranker::assign([10, 10, 7, 7, 7, 3], static fn (int $v): int => $v);

        $this->assertSame([1, 1, 3, 3, 3, 6], $ranks);
    }

    public function testFloatsWithinEpsilonShareARank(): void
    {
        $ranks = Ranker::assign([10.00004, 10.0, 9.5], static fn (float $v): float => $v);

        $this->assertSame([1, 1, 3], $ranks);
    }

    public function testEmptyListYieldsNoRanks(): void
    {
        $this->assertSame([], Ranker::assign([], static fn (int $v): int => $v));
    }
}
