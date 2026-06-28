<?php

namespace App\Tests\Controller;

use App\Entity\Prediction;
use App\Tests\FixturesWebTestCase;

class MatchViewTest extends FixturesWebTestCase
{
    private function predict(string $email, \App\Entity\FootballMatch $match): void
    {
        $prediction = (new Prediction())
            ->setUser($this->user($email))
            ->setFootballMatch($match)
            ->setHomeScore(2)
            ->setAwayScore(1)
            ->setAdvancingSide(\App\Entity\FootballMatch::SIDE_HOME)
            ->setUpdatedAt(new \DateTimeImmutable());
        $this->em->persist($prediction);
        $this->em->flush();
    }

    public function testPredictionsHiddenBeforeKickoff(): void
    {
        $open = $this->openMatch();
        $this->predict('anne@trepiedi.test', $open); // er is iets dat verborgen kan worden

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $this->client->request('GET', '/wedstrijd/' . $open->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.alert-info', 'zichtbaar zodra de wedstrijd is begonnen');
        // De consensus mag vóór de aftrap niet lekken.
        $this->assertSelectorNotExists('.progress-bar');
    }

    public function testNoPredictionsMessage(): void
    {
        $open = $this->openMatch(); // open wedstrijd zonder voorspellingen

        $this->client->request('GET', '/wedstrijd/' . $open->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'geen voorspellingen gedaan');
    }

    public function testMatchPointsReflectRoundWeight(): void
    {
        $match = $this->lockedMatch();
        $match->getRound()->setWeight(2.0);
        $this->em->flush();

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/wedstrijd/' . $match->getId());

        $this->assertResponseIsSuccessful();
        // Anne voorspelde deze wedstrijd perfect (6); met rondegewicht 2 toont de badge +12.
        $this->assertStringContainsString('+12', $crawler->filter('tbody')->text());
    }

    public function testPredictionsVisibleAfterKickoff(): void
    {
        $locked = $this->lockedMatch();

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/wedstrijd/' . $locked->getId());

        $this->assertResponseIsSuccessful();
        // Consensusbalken en de voorspellingen van anderen zijn nu zichtbaar.
        $this->assertSelectorExists('.progress-bar');
        $this->assertGreaterThan(0, $crawler->filter('tbody tr')->count());
    }

    public function testPredictionsSortedByPointsWhenResultKnown(): void
    {
        $match = $this->lockedMatch();
        $this->assertTrue($match->hasResult(), 'Testaanname: deze fixture-wedstrijd heeft een uitslag.');

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/wedstrijd/' . $match->getId());

        $this->assertResponseIsSuccessful();

        $points = $crawler->filter('tbody .badge.text-bg-success')->each(
            static fn ($node): int => (int) ltrim($node->text(), '+')
        );
        $this->assertNotEmpty($points);

        $sorted = $points;
        rsort($sorted);
        $this->assertSame($sorted, $points, 'Voorspellingen horen op aflopend aantal punten te staan (meeste eerst).');
    }
}
