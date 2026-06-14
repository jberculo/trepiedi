<?php

namespace App\Tests\Controller;

use App\Entity\Prediction;
use App\Tests\FixturesWebTestCase;

/**
 * Uitgelogde bezoekers krijgen een read-only versie: klassement, profielen en
 * wedstrijden zijn te bekijken, maar voorspellen en beheer niet.
 */
class PublicAccessTest extends FixturesWebTestCase
{
    public function testHomepageIsPublicLeaderboard(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Klassement');
        $this->assertSelectorTextContains('body', 'Anne');
    }

    public function testProfileIsPublicButHidesUpcoming(): void
    {
        $anneSlug = $this->user('anne@trepiedi.test')->getSlug();

        $this->client->request('GET', '/speler/' . $anneSlug);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Anne');
        // Een anonieme bezoeker is niet de eigenaar: komende voorspellingen blijven verborgen.
        $this->assertSelectorTextContains('body', 'zichtbaar na de aftrap');
        // Verleden wedstrijden tonen wél punten.
        $this->assertSelectorExists('.badge.text-bg-success');
    }

    public function testMatchPageIsPublic(): void
    {
        $locked = $this->lockedMatch();

        $this->client->request('GET', '/wedstrijd/' . $locked->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.progress-bar');
    }

    public function testDashboardRequiresLogin(): void
    {
        $this->client->request('GET', '/voorspellen');
        $this->assertResponseRedirects('/login');
    }

    public function testAdminRequiresLogin(): void
    {
        $this->client->request('GET', '/admin');
        $this->assertResponseRedirects('/login');
    }

    public function testMatchesPageIsPublicAndShowsResults(): void
    {
        $crawler = $this->client->request('GET', '/wedstrijden');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('table tbody tr');
        // Een gespeelde wedstrijd toont een uitslag (Nederland – Polen 2 – 1).
        $body = $crawler->filter('table')->text();
        $this->assertStringContainsString('Nederland', $body);
        $this->assertStringContainsString('2 – 1', $body);

        // Onderverdeeld per ronde, oudste ronde eerst: de eerste kop is de achtste finales.
        $this->assertStringContainsString('Achtste finales', $crawler->filter('h2')->first()->text());
    }

    public function testCannotPredictWhenLoggedOut(): void
    {
        $open = $this->openMatch();
        $matchId = $open->getId();

        $this->client->request('POST', '/voorspelling/' . $matchId . '/opslaan', [
            'prediction' => ['homeScore' => 1, 'awayScore' => 0, 'advancingSide' => 'home'],
        ]);

        $this->assertResponseRedirects('/login');

        $this->em->clear();
        $count = $this->em->getRepository(Prediction::class)->count(['footballMatch' => $matchId]);
        $this->assertSame(0, $count, 'Een uitgelogde bezoeker mag geen voorspelling kunnen opslaan.');
    }
}
