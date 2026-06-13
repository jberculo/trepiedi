<?php

namespace App\Tests\Entity;

use App\Entity\FootballMatch;
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
        $open = (new FootballMatch())->setHomeTeam('A')->setAwayTeam('B');
        $this->assertFalse($open->hasResult());

        $scoredNotFinal = (new FootballMatch())
            ->setHomeTeam('A')->setAwayTeam('B')
            ->setHomeScore(1)->setAwayScore(0)->setFinished(false);
        $this->assertFalse($scoredNotFinal->hasResult(), 'Niet definitief telt niet als uitslag.');

        $final = (new FootballMatch())
            ->setHomeTeam('A')->setAwayTeam('B')
            ->setHomeScore(1)->setAwayScore(0)->setAdvancingSide(FootballMatch::SIDE_HOME)->setFinished(true);
        $this->assertTrue($final->hasResult());
        $this->assertSame('A', $final->getAdvancingTeam(), 'Doorgaande ploeg = naam van de thuiskant.');
    }
}
