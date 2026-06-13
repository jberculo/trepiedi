<?php

namespace App\Tests\Entity;

use App\Entity\FootballMatch;
use App\Entity\Team;
use PHPUnit\Framework\TestCase;

class FootballMatchTest extends TestCase
{
    public function testIsLockedFromKickoff(): void
    {
        $now = new \DateTimeImmutable('2026-06-12 20:00:00');

        $future = (new FootballMatch())->setKickoffAt($now->modify('+1 hour'));
        $this->assertFalse($future->isLocked($now), 'Voor de aftrap is de wedstrijd open.');

        $started = (new FootballMatch())->setKickoffAt($now->modify('-1 second'));
        $this->assertTrue($started->isLocked($now), 'Vanaf de aftrap is de wedstrijd gesloten.');

        $exact = (new FootballMatch())->setKickoffAt($now);
        $this->assertTrue($exact->isLocked($now), 'Exact op de aftrap is gesloten.');
    }

    public function testHasResult(): void
    {
        $teamA = (new Team())->setName('A');
        $teamB = (new Team())->setName('B');

        $open = (new FootballMatch())->setHomeTeam($teamA)->setAwayTeam($teamB);
        $this->assertFalse($open->hasResult());

        $scoredNotFinal = (new FootballMatch())
            ->setHomeTeam($teamA)->setAwayTeam($teamB)
            ->setHomeScore(1)->setAwayScore(0)->setFinished(false);
        $this->assertFalse($scoredNotFinal->hasResult(), 'Niet definitief telt niet als uitslag.');

        $final = (new FootballMatch())
            ->setHomeTeam($teamA)->setAwayTeam($teamB)
            ->setHomeScore(1)->setAwayScore(0)->setAdvancingTeam($teamA)->setFinished(true);
        $this->assertTrue($final->hasResult());
    }
}
