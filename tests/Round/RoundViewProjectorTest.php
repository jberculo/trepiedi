<?php

namespace App\Tests\Round;

use App\Entity\FootballMatch;
use App\Entity\Round;
use App\Repository\RoundRepository;
use App\Round\RoundViewProjector;
use PHPUnit\Framework\TestCase;

class RoundViewProjectorTest extends TestCase
{
    public function testProjectKeepsRoundGroupingAndProjectsMatches(): void
    {
        $round1 = (new Round())->setName('Achtste finales')->setSortOrder(1);
        $round2 = (new Round())->setName('Kwartfinales')->setSortOrder(2);

        $m1 = (new FootballMatch())->setHomeTeam('A')->setAwayTeam('B')->setKickoffAt(new \DateTimeImmutable('+1 day'));
        $m2 = (new FootballMatch())->setHomeTeam('C')->setAwayTeam('D')->setKickoffAt(new \DateTimeImmutable('+2 days'));
        $m3 = (new FootballMatch())->setHomeTeam('E')->setAwayTeam('F')->setKickoffAt(new \DateTimeImmutable('+3 days'));
        $round1->addMatch($m1)->addMatch($m2);
        $round2->addMatch($m3);

        $repo = $this->createMock(RoundRepository::class);
        $repo->expects($this->once())->method('findAllForRoundViews')->willReturn([$round1, $round2]);

        $rows = (new RoundViewProjector($repo))->project(
            static fn (FootballMatch $match, Round $round): array => ['label' => $round->getName() . ': ' . (string) $match]
        );

        $this->assertCount(2, $rows);
        $this->assertSame($round1, $rows[0]['round']);
        $this->assertCount(2, $rows[0]['items']);
        $this->assertSame('Achtste finales: A - B', $rows[0]['items'][0]['label']);
        $this->assertSame('Kwartfinales: E - F', $rows[1]['items'][0]['label']);
    }
}
