<?php

namespace App\Tests\Util;

use App\Entity\FootballMatch;
use App\Util\MatchOutcome;
use PHPUnit\Framework\TestCase;

class MatchOutcomeTest extends TestCase
{
    public function testScoreWinner(): void
    {
        $this->assertSame(FootballMatch::SIDE_HOME, MatchOutcome::scoreWinner(2, 1));
        $this->assertSame(FootballMatch::SIDE_AWAY, MatchOutcome::scoreWinner(1, 2));
        $this->assertNull(MatchOutcome::scoreWinner(1, 1), 'Gelijkspel heeft geen score-winnaar.');
        $this->assertNull(MatchOutcome::scoreWinner(null, 1), 'Onvolledige score heeft geen winnaar.');
    }

    public function testIsInconsistent(): void
    {
        $this->assertFalse(MatchOutcome::isInconsistent(2, 1, FootballMatch::SIDE_HOME), 'Score-winnaar gaat door → consistent.');
        $this->assertTrue(MatchOutcome::isInconsistent(2, 1, FootballMatch::SIDE_AWAY), 'Score-verliezer gaat door → tegenstrijdig.');
        $this->assertFalse(MatchOutcome::isInconsistent(1, 1, FootballMatch::SIDE_AWAY), "Gelijkspel is nooit tegenstrijdig (penalty's).");
        $this->assertFalse(MatchOutcome::isInconsistent(2, 1, null), 'Zonder doorgaande ploeg geen oordeel.');
    }
}
