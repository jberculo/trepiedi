<?php

namespace App\Tests\Controller;

use App\Entity\Pool;
use App\Tests\FixturesWebTestCase;

/**
 * Na het inloggen begint een speler standaard in de standaardpoule; via het
 * profiel kan hij zelf een andere poule kiezen.
 */
class PoolPreferenceTest extends FixturesWebTestCase
{
    public function testLoginResetsToDefaultPool(): void
    {
        // Anne had laatst Kantoor actief.
        $anne = $this->user('anne@trepiedi.test');
        $anne->setActivePool($this->poolByCode('kantoor'));
        $this->em->flush();

        // Inloggen via het formulier.
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Inloggen')->form([
            'email' => 'anne@trepiedi.test',
            'password' => 'test',
        ]));
        $this->client->followRedirect();

        $this->em->clear();
        $this->assertNull(
            $this->user('anne@trepiedi.test')->getActivePool(),
            'Na inloggen valt de actieve poule terug op de standaard.'
        );
    }

    public function testProfileShowsPoolSwitcherForMultiPoolUser(): void
    {
        // Anne zit in twee poules → switch zichtbaar in het profiel.
        $this->client->loginUser($this->user('anne@trepiedi.test'));
        $crawler = $this->client->request('GET', '/account');
        $this->assertGreaterThan(0, $crawler->filter('a[href="/poule/wissel/kantoor"]')->count());

        // Bram zit in één poule → geen switch.
        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/account');
        $this->assertSame(0, $crawler->filter('a[href^="/poule/wissel/"]')->count());
    }

    public function testProfileSwitchChangesActivePool(): void
    {
        $this->client->loginUser($this->user('anne@trepiedi.test'));
        $this->client->request('GET', '/account');
        $this->client->request('GET', '/poule/wissel/kantoor');

        $this->em->clear();
        $this->assertSame('kantoor', $this->user('anne@trepiedi.test')->getActivePool()?->getCode());
    }

    private function poolByCode(string $code): Pool
    {
        return $this->em->getRepository(Pool::class)->findOneBy(['code' => $code]);
    }
}
