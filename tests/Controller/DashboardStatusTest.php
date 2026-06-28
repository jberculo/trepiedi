<?php

namespace App\Tests\Controller;

use App\Entity\FootballMatch;
use App\Tests\FixturesWebTestCase;

class DashboardStatusTest extends FixturesWebTestCase
{
    public function testStartedMatchShowsStartedBadge(): void
    {
        $match = $this->openMatch();
        $match->setKickoffAt(new \DateTimeImmutable('-1 hour'));
        $this->em->flush();

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/voorspellen');

        $this->assertResponseIsSuccessful();
        $card = $crawler->filter('#match-' . $match->getId())->text();
        $this->assertStringContainsString('Gestart', $card);
        $this->assertStringNotContainsString('Wachten op uitslag', $card);
    }

    public function testLongStartedMatchShowsAwaitingResultBadge(): void
    {
        $match = $this->openMatch();
        $match->setKickoffAt(new \DateTimeImmutable('-3 hours'));
        $this->em->flush();

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/voorspellen');

        $this->assertResponseIsSuccessful();
        $card = $crawler->filter('#match-' . $match->getId())->text();
        $this->assertStringContainsString('Wachten op uitslag', $card);
        $this->assertStringNotContainsString('Gestart', $card);
    }

    public function testDashboardRendersInconsistencyModal(): void
    {
        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $this->client->request('GET', '/voorspellen');

        $this->assertResponseIsSuccessful();
        // De bevestigings-modal (geen js-confirm) staat klaar in de pagina.
        $this->assertSelectorExists('#inconsistent-modal');
        $this->assertSelectorExists('#inconsistent-modal-confirm');
    }
}
