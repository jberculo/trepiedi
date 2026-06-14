<?php

namespace App\Tests\Controller;

use App\Entity\Pool;
use App\Tests\FixturesWebTestCase;

class AdminPoolCrudTest extends FixturesWebTestCase
{
    public function testRequiresAdmin(): void
    {
        $this->client->loginUser($this->user('anne@trepiedi.test'));
        $this->client->request('GET', '/admin/poules');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testIndexListsPools(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/poules');

        $this->assertResponseIsSuccessful();
        $body = $crawler->filter('body')->text();
        $this->assertStringContainsString('Algemeen', $body);
        $this->assertStringContainsString('Kantoor', $body);
    }

    public function testCreatePool(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/poules/nieuw');
        $form = $crawler->selectButton('Opslaan')->form([
            'pool[name]' => 'Familie',
            'pool[code]' => 'familie',
        ]);
        $this->client->submit($form);
        $this->assertResponseRedirects('/admin/poules');

        $this->em->clear();
        $pool = $this->em->getRepository(Pool::class)->findOneBy(['code' => 'familie']);
        $this->assertNotNull($pool);
        $this->assertSame('Familie', $pool->getName());
        $this->assertFalse($pool->isDefault());
    }

    public function testCreatingDefaultUnsetsPreviousDefault(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/poules/nieuw');
        $form = $crawler->selectButton('Opslaan')->form([
            'pool[name]' => 'Nieuwe standaard',
            'pool[code]' => 'nieuw',
        ]);
        $form['pool[default]']->tick();
        $this->client->submit($form);

        $this->em->clear();
        $repo = $this->em->getRepository(Pool::class);
        $this->assertTrue($repo->findOneBy(['code' => 'nieuw'])->isDefault());
        $this->assertFalse($repo->findOneBy(['code' => 'algemeen'])->isDefault(), 'Oude standaard is niet langer standaard.');
        $this->assertCount(1, $repo->findBy(['isDefault' => true]), 'Hooguit één standaardpoule.');
    }

    public function testEditPoolName(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $kantoor = $this->em->getRepository(Pool::class)->findOneBy(['code' => 'kantoor']);

        $crawler = $this->client->request('GET', '/admin/poules/' . $kantoor->getId() . '/bewerken');
        $form = $crawler->selectButton('Opslaan')->form();
        $form['pool[name]'] = 'Het Kantoor';
        $this->client->submit($form);

        $this->em->clear();
        $this->assertSame('Het Kantoor', $this->em->getRepository(Pool::class)->findOneBy(['code' => 'kantoor'])->getName());
    }

    public function testDefaultPoolHasNoDeleteButton(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/poules');

        $default = $this->em->getRepository(Pool::class)->findOneBy(['isDefault' => true]);
        $this->assertSame(
            0,
            $crawler->filter('form[action="/admin/poules/' . $default->getId() . '/verwijderen"]')->count(),
            'De standaardpoule heeft geen verwijderknop.'
        );
    }

    public function testDeleteNonDefaultPool(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $kantoor = $this->em->getRepository(Pool::class)->findOneBy(['code' => 'kantoor']);
        $id = $kantoor->getId();

        $crawler = $this->client->request('GET', '/admin/poules');
        $form = $crawler->filter('form[action="/admin/poules/' . $id . '/verwijderen"]')->form();
        $this->client->submit($form);
        $this->assertResponseRedirects('/admin/poules');

        $this->em->clear();
        $this->assertNull($this->em->getRepository(Pool::class)->find($id), 'Kantoor is verwijderd.');
    }

    public function testRemoveMember(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $kantoor = $this->em->getRepository(Pool::class)->findOneBy(['code' => 'kantoor']);
        $anne = $this->user('anne@trepiedi.test');
        $this->assertTrue($anne->isInPool($kantoor));

        $crawler = $this->client->request('GET', '/admin/poules/' . $kantoor->getId() . '/leden');
        $form = $crawler->filter('form[action="/admin/poules/' . $kantoor->getId() . '/leden/' . $anne->getId() . '/verwijderen"]')->form();
        $this->client->submit($form);
        $this->assertResponseRedirects();

        $this->em->clear();
        $kantoor = $this->em->getRepository(Pool::class)->findOneBy(['code' => 'kantoor']);
        $anne = $this->user('anne@trepiedi.test');
        $this->assertFalse($anne->isInPool($kantoor), 'Anne is uit Kantoor verwijderd.');
    }
}
