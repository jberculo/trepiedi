<?php

namespace App\Tests\Controller;

use App\Tests\FixturesWebTestCase;

class LocaleTest extends FixturesWebTestCase
{
    public function testDefaultLocaleIsDutch(): void
    {
        $this->assertSame('nl', $this->user('anne@trepiedi.test')->getLocale());

        // Niet-ingelogde bezoeker krijgt Nederlands.
        $this->client->request('GET', '/');
        $this->assertSelectorTextContains('.navbar', 'Klassement');
    }

    public function testProfileLanguageAppliesAfterLogin(): void
    {
        $bram = $this->user('bram@trepiedi.test');
        $bram->setLocale('en');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Inloggen')->form([
            'email' => 'bram@trepiedi.test',
            'password' => 'test',
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();

        // De voorkeurstaal uit het profiel maakt de site Engels.
        $this->assertSelectorTextContains('.navbar', 'Standings');
    }

    public function testAccountPageLanguageSwitcherPersistsAndApplies(): void
    {
        $this->client->loginUser($this->user('bram@trepiedi.test'));

        $crawler = $this->client->request('GET', '/account');
        // De taalschakelaar op de accountpagina (vlag-link) wisselt naar Engels.
        $this->client->click($crawler->filter('a[href="/taal/en"]')->link());
        $this->client->followRedirect();

        // Direct toegepast …
        $this->assertSelectorTextContains('.navbar', 'Standings');

        // … en bewaard in het profiel.
        $this->em->clear();
        $this->assertSame('en', $this->user('bram@trepiedi.test')->getLocale());
    }

    public function testSwitcherUpdatesProfileForLoggedInUser(): void
    {
        $this->client->loginUser($this->user('bram@trepiedi.test'));

        $this->client->request('GET', '/taal/en');

        $this->em->clear();
        $this->assertSame('en', $this->user('bram@trepiedi.test')->getLocale(), 'Taalwissel moet de profielvoorkeur bijwerken.');
    }
}
