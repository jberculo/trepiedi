<?php

namespace App\Tests\Controller;

use App\Tests\FixturesWebTestCase;

class UserViewTest extends FixturesWebTestCase
{
    public function testOwnProfileShowsEverythingWithPoints(): void
    {
        $anneSlug = $this->user('anne@trepiedi.test')->getSlug();

        $this->client->loginUser($this->user('anne@trepiedi.test'));
        $this->client->request('GET', '/speler/' . $anneSlug);

        $this->assertResponseIsSuccessful();
        // Eigen profiel verbergt niets.
        $this->assertSelectorTextNotContains('body', 'zichtbaar na de aftrap');
        // Gespeelde wedstrijden in het verleden tonen punten.
        $this->assertSelectorExists('.badge.text-bg-success');
    }

    public function testWeightedTotalHasNoTrailingDecimal(): void
    {
        $anneSlug = $this->user('anne@trepiedi.test')->getSlug();

        $this->client->loginUser($this->user('anne@trepiedi.test'));
        $crawler = $this->client->request('GET', '/speler/' . $anneSlug);

        $this->assertResponseIsSuccessful();
        // Gehele punten horen als "30" te tonen, niet als "30,0".
        $this->assertStringNotContainsString(',0', $crawler->filter('.text-end .h3')->text());
    }

    public function testOtherProfileHidesUpcomingPredictions(): void
    {
        $anneSlug = $this->user('anne@trepiedi.test')->getSlug();

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $this->client->request('GET', '/speler/' . $anneSlug);

        $this->assertResponseIsSuccessful();
        // Komende wedstrijden van een ander blijven verborgen tot de aftrap.
        $this->assertSelectorTextContains('body', 'zichtbaar na de aftrap');
        // Verleden wedstrijden tonen wél de voorspelling + punten.
        $this->assertSelectorExists('.badge.text-bg-success');
    }
}
