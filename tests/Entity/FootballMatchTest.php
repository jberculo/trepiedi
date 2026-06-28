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

    public function testIsAwaitingResultAfterGracePeriod(): void
    {
        $now = new \DateTimeImmutable('2026-06-12 20:00:00');

        $notStarted = (new FootballMatch())->setKickoffAt($now->modify('+1 hour'));
        $this->assertFalse($notStarted->isAwaitingResult($now), 'Voor de aftrap: niet wachtend.');

        $justStarted = (new FootballMatch())->setKickoffAt($now->modify('-1 hour'));
        $this->assertFalse($justStarted->isAwaitingResult($now), 'Binnen 2 uur na de aftrap: nog "gestart".');

        $atGrace = (new FootballMatch())->setKickoffAt($now->modify('-2 hours'));
        $this->assertTrue($atGrace->isAwaitingResult($now), 'Precies 2 uur na de aftrap: wachtend op uitslag.');

        $longStarted = (new FootballMatch())->setKickoffAt($now->modify('-3 hours'));
        $this->assertTrue($longStarted->isAwaitingResult($now), 'Ruim na de aftrap zonder uitslag: wachtend.');

        $finished = (new FootballMatch())
            ->setKickoffAt($now->modify('-3 hours'))
            ->setHomeScore(1)->setAwayScore(0)->setAdvancingSide(FootballMatch::SIDE_HOME)->setFinished(true);
        $this->assertFalse($finished->isAwaitingResult($now), 'Met definitieve uitslag is de wedstrijd niet meer wachtend.');
    }

    public function testHasInconsistentResult(): void
    {
        $consistent = (new FootballMatch())
            ->setHomeScore(2)->setAwayScore(1)->setAdvancingSide(FootballMatch::SIDE_HOME);
        $this->assertFalse($consistent->hasInconsistentResult(), 'Score-winnaar gaat door → consistent.');

        $inconsistent = (new FootballMatch())
            ->setHomeScore(2)->setAwayScore(1)->setAdvancingSide(FootballMatch::SIDE_AWAY);
        $this->assertTrue($inconsistent->hasInconsistentResult(), 'Thuis wint de score maar uit gaat door → tegenstrijdig.');

        $draw = (new FootballMatch())
            ->setHomeScore(1)->setAwayScore(1)->setAdvancingSide(FootballMatch::SIDE_AWAY);
        $this->assertFalse($draw->hasInconsistentResult(), "Gelijkspel is nooit tegenstrijdig (penalty's).");

        $incomplete = (new FootballMatch())->setHomeScore(2)->setAwayScore(1);
        $this->assertFalse($incomplete->hasInconsistentResult(), 'Zonder doorgaande ploeg geen oordeel.');
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
