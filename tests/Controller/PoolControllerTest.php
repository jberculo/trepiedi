<?php

namespace App\Tests\Controller;

use App\Entity\Pool;
use App\Entity\User;
use App\Pool\PoolEnroller;
use App\Tests\FixturesWebTestCase;

/**
 * Poules (mini-leagues): standaardpoule, scopen van het klassement, inschrijven
 * via code, en overschakelen.
 */
class PoolControllerTest extends FixturesWebTestCase
{
    public function testDefaultPoolLeaderboardShowsEveryone(): void
    {
        $crawler = $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $body = $crawler->filter('body')->text();
        foreach (['Anne', 'Bram', 'Chris', 'Diana'] as $name) {
            $this->assertStringContainsString($name, $body, "$name hoort in de standaardpoule.");
        }
    }

    public function testSwitchingToKantoorScopesLeaderboard(): void
    {
        // Anne zit in Algemeen én Kantoor; Bram/Diana alleen in Algemeen.
        $this->client->loginUser($this->user('anne@trepiedi.test'));

        $this->client->request('GET', '/poule/wissel/kantoor');
        $this->assertResponseRedirects();

        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $table = $crawler->filter('.lb-table')->text();

        $this->assertStringContainsString('Chris', $table, 'Kantoor bevat Anne en Chris.');
        $this->assertStringNotContainsString('Bram', $table, 'Bram zit niet in Kantoor.');
        $this->assertStringNotContainsString('Diana', $table, 'Diana zit niet in Kantoor.');
    }

    public function testJoinByCodeAddsMembershipAndSwitches(): void
    {
        $this->client->loginUser($this->user('bram@trepiedi.test'));

        $this->client->request('GET', '/poule/inschrijven/kantoor');
        $this->assertResponseRedirects();

        $this->em->clear();
        $bram = $this->user('bram@trepiedi.test');
        $kantoor = $this->em->getRepository(Pool::class)->findOneBy(['code' => 'kantoor']);
        $this->assertTrue($bram->isInPool($kantoor), 'Bram is nu lid van Kantoor.');
        $this->assertSame($kantoor->getId(), $bram->getActivePool()?->getId(), 'Kantoor is meteen actief.');
    }

    public function testJoinUnknownCodeFlashesErrorAndRedirects(): void
    {
        $this->client->loginUser($this->user('bram@trepiedi.test'));

        $this->client->request('GET', '/poule/inschrijven/bestaat-niet');
        $this->assertResponseRedirects('/');
    }

    public function testJoinWhileLoggedOutStashesCodeAndRedirectsToRegister(): void
    {
        $this->client->request('GET', '/poule/inschrijven/kantoor');

        $this->assertResponseRedirects('/register');
        $this->assertSame('kantoor', $this->client->getRequest()->getSession()->get(PoolEnroller::SESSION_KEY));
    }

    public function testSwitchToPoolYouAreNotMemberOfIsRejected(): void
    {
        // Bram zit niet in Kantoor.
        $this->client->loginUser($this->user('bram@trepiedi.test'));

        $this->client->request('GET', '/poule/wissel/kantoor');
        $this->assertResponseRedirects('/');

        $this->em->clear();
        $bram = $this->user('bram@trepiedi.test');
        $this->assertFalse($bram->isInPool($this->poolByCode('kantoor')), 'Wisselen maakt geen lid.');
    }

    public function testRegistrationWithStashedCodeJoinsThatPoolOnly(): void
    {
        // Eerst de code stashen door de join-link uitgelogd te bezoeken.
        $this->client->request('GET', '/poule/inschrijven/kantoor');

        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->selectButton('Registreren')->form([
            'registration_form[displayName]' => 'Evert',
            'registration_form[email]' => 'evert@trepiedi.test',
            'registration_form[plainPassword]' => 'geheim123',
        ]);
        $this->client->submit($form);

        $this->em->clear();
        $evert = $this->user('evert@trepiedi.test');
        $this->assertNotNull($evert);
        $this->assertTrue($evert->isInPool($this->poolByCode('kantoor')), 'Met code → in Kantoor.');
        $this->assertSame('kantoor', $evert->getActivePool()?->getCode());
        $this->assertFalse($evert->isInPool($this->poolByCode('algemeen')), 'Met code niet óók in de standaardpoule.');
    }

    public function testRegistrationWithoutCodeJoinsDefaultPool(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->selectButton('Registreren')->form([
            'registration_form[displayName]' => 'Fleur',
            'registration_form[email]' => 'fleur@trepiedi.test',
            'registration_form[plainPassword]' => 'geheim123',
        ]);
        $this->client->submit($form);

        $this->em->clear();
        $fleur = $this->user('fleur@trepiedi.test');
        $this->assertNotNull($fleur);
        $this->assertTrue($fleur->isInPool($this->poolByCode('algemeen')), 'Zonder code → standaardpoule.');
    }

    public function testNavbarSwitcherOnlyForMultiPoolUser(): void
    {
        // Anne (2 poules) ziet de switcher …
        $this->client->loginUser($this->user('anne@trepiedi.test'));
        $crawler = $this->client->request('GET', '/');
        $this->assertGreaterThan(0, $crawler->filter('a[href="/poule/wissel/kantoor"]')->count());

        // … Bram (1 poule) niet.
        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/');
        $this->assertSame(0, $crawler->filter('a[href^="/poule/wissel/"]')->count());
    }

    private function poolByCode(string $code): Pool
    {
        return $this->em->getRepository(Pool::class)->findOneBy(['code' => $code]);
    }
}
