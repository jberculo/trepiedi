<?php

namespace App\Tests\Controller;

use App\Entity\FootballMatch;
use App\Entity\Prediction;
use App\Repository\FootballMatchRepository;
use App\Tests\FixturesWebTestCase;

class PredictReminderTest extends FixturesWebTestCase
{
    private const SELECTOR = '[data-dismiss-store="trepiedi:predict-reminder-dismissed"]';

    public function testReminderShownWhenOpenMatchesAreUnpredicted(): void
    {
        // De fixtures hebben open (toekomstige) wedstrijden zonder voorspelling.
        $this->client->loginUser($this->user('anne@trepiedi.test'));
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $this->assertCount(1, $crawler->filter(self::SELECTOR), 'Herinnering hoort te verschijnen bij openstaande voorspellingen.');
    }

    public function testCountMatchesOpenUnpredicted(): void
    {
        $repo = static::getContainer()->get(FootballMatchRepository::class);
        $anne = $this->user('anne@trepiedi.test');
        $now = new \DateTimeImmutable();

        $expected = 0;
        foreach ($repo->findAll() as $match) {
            if ($match->isActive() && $match->getKickoffAt() > $now) {
                ++$expected;
            }
        }

        $this->assertGreaterThan(0, $expected, 'Fixtures horen open wedstrijden te bevatten.');
        $this->assertSame($expected, $repo->countOpenWithoutPredictionForUser($anne, $now));
    }

    public function testNoReminderWhenEverythingPredicted(): void
    {
        $anne = $this->user('anne@trepiedi.test');
        $predictions = $this->em->getRepository(Prediction::class);
        $now = new \DateTimeImmutable();

        foreach ($this->em->getRepository(FootballMatch::class)->findBy(['active' => true]) as $match) {
            if ($match->getKickoffAt() > $now && $predictions->findOneForUserAndMatch($anne, $match) === null) {
                $this->em->persist(
                    (new Prediction())->setUser($anne)->setFootballMatch($match)
                        ->setHomeScore(1)->setAwayScore(0)->setAdvancingSide(FootballMatch::SIDE_HOME)
                        ->setUpdatedAt($now)
                );
            }
        }
        $this->em->flush();

        $this->client->loginUser($anne);
        $crawler = $this->client->request('GET', '/');

        $this->assertCount(0, $crawler->filter(self::SELECTOR), 'Zonder openstaande voorspellingen geen herinnering.');
    }
}
