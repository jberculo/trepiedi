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
    public function testLoginKeepsChosenPoolAsDefault(): void
    {
        // Anne koos eerder Kantoor; dat blijft haar standaard, ook na opnieuw inloggen.
        $anne = $this->user('anne@trepiedi.test');
        $anne->setActivePool($this->poolByCode('kantoor'));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Inloggen')->form([
            'email' => 'anne@trepiedi.test',
            'password' => 'test',
        ]));
        $this->client->followRedirect();

        $this->em->clear();
        $this->assertSame(
            'kantoor',
            $this->user('anne@trepiedi.test')->getActivePool()?->getCode(),
            'De zelfgekozen poule blijft onthouden na inloggen.'
        );
    }

    public function testWithoutChoiceLoginShowsDefaultPool(): void
    {
        // Geen eigen keuze → PoolContext toont de standaardpoule.
        $bram = $this->user('bram@trepiedi.test');
        $this->assertNull($bram->getActivePool());

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Inloggen')->form([
            'email' => 'bram@trepiedi.test',
            'password' => 'test',
        ]));
        $this->client->followRedirect();

        $this->em->clear();
        $this->assertNull($this->user('bram@trepiedi.test')->getActivePool(), 'Zonder keuze blijft het de standaard (null).');
    }

    public function testProfileShowsPoolSwitcherForMultiPoolUser(): void
    {
        // Anne zit in twee poules → switch zichtbaar in het profiel.
        $this->client->loginUser($this->user('anne@trepiedi.test'));
        $crawler = $this->client->request('GET', '/account');
        $this->assertGreaterThan(0, $crawler->filter('form[action="/poule/wissel/kantoor"]')->count());

        // Bram zit in één poule → geen switch.
        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/account');
        $this->assertSame(0, $crawler->filter('form[action^="/poule/wissel/"]')->count());
    }

    public function testProfileSwitchChangesActivePool(): void
    {
        $this->client->loginUser($this->user('anne@trepiedi.test'));
        $this->client->request('GET', '/account');
        $crawler = $this->client->request('GET', '/account');
        $form = $crawler->filter('form[action="/poule/wissel/kantoor"]')->form();
        $this->client->submit($form);

        $this->em->clear();
        $this->assertSame('kantoor', $this->user('anne@trepiedi.test')->getActivePool()?->getCode());
    }

    public function testArchivedPoolsAreExcludedFromSwitchers(): void
    {
        $this->poolByCode('kantoor')->archive();
        $this->em->flush();

        $this->client->loginUser($this->user('anne@trepiedi.test'));

        $crawler = $this->client->request('GET', '/');
        $this->assertSame(0, $crawler->filter('form[action^="/poule/wissel/"]')->count());

        $crawler = $this->client->request('GET', '/account');
        $this->assertSame(0, $crawler->filter('form[action^="/poule/wissel/"]')->count());
    }

    public function testLoginWithStashedCodeJoinsPoolAndShowsFeedback(): void
    {
        $this->client->request('GET', '/poule/inschrijven/kantoor');

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Inloggen')->form([
            'email' => 'bram@trepiedi.test',
            'password' => 'test',
        ]));
        $crawler = $this->client->followRedirect();

        $this->assertStringContainsString('Je bent nu lid van deze poule.', $crawler->filter('body')->text());
        $this->assertNull($this->client->getRequest()->getSession()->get('pool_code'));

        $this->em->clear();
        $this->assertTrue($this->user('bram@trepiedi.test')->isInPool($this->poolByCode('kantoor')));
        $this->assertSame('kantoor', $this->user('bram@trepiedi.test')->getActivePool()?->getCode());
    }

    public function testLoginWithInvalidatedStashedCodeShowsError(): void
    {
        $this->client->request('GET', '/poule/inschrijven/kantoor');
        $this->poolByCode('kantoor')->archive();
        $this->em->flush();

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Inloggen')->form([
            'email' => 'bram@trepiedi.test',
            'password' => 'test',
        ]));
        $crawler = $this->client->followRedirect();

        $this->assertStringContainsString('je bent niet aan de poule toegevoegd', mb_strtolower($crawler->filter('body')->text()));
        $this->assertNull($this->client->getRequest()->getSession()->get('pool_code'));

        $this->em->clear();
        $this->assertFalse($this->user('bram@trepiedi.test')->isInPool($this->poolByCode('kantoor')));
    }

    private function poolByCode(string $code): Pool
    {
        return $this->em->getRepository(Pool::class)->findOneBy(['code' => $code]);
    }
}
