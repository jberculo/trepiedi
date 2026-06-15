<?php

namespace App\Tests\Controller;

use App\Entity\Pool;
use App\Entity\User;
use App\Pool\PoolEnroller;
use App\Tests\FixturesWebTestCase;

/**
 * Soft-delete van poules (archiveren/herstellen), de wees-melding en het
 * blokkeren van registratie bij een foutieve/verlopen poule-link.
 */
class PoolSoftDeleteTest extends FixturesWebTestCase
{
    public function testAdminCanArchiveAndItDisappearsFromJoin(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $kantoor = $this->poolByCode('kantoor');

        // Archiveren via het beheer.
        $crawler = $this->client->request('GET', '/admin/poules');
        $form = $crawler->filter('form[action="/admin/poules/' . $kantoor->getId() . '/archiveren"]')->form();
        $this->client->submit($form);
        $this->assertResponseRedirects('/admin/poules');

        $this->em->clear();
        $kantoor = $this->poolByCode('kantoor');
        $this->assertTrue($kantoor->isArchived(), 'Poule is gearchiveerd (soft-delete), niet verwijderd.');

        // Inschrijven op een gearchiveerde poule kan niet meer.
        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $this->client->request('GET', '/poule/inschrijven/kantoor');
        $this->assertResponseRedirects('/');
        $this->em->clear();
        $this->assertFalse($this->user('bram@trepiedi.test')->isInPool($this->poolByCode('kantoor')));
    }

    public function testRestoreMakesPoolActiveAgain(): void
    {
        $kantoor = $this->poolByCode('kantoor');
        $kantoor->archive();
        $this->em->flush();

        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/poules');
        $form = $crawler->filter('form[action="/admin/poules/' . $kantoor->getId() . '/herstellen"]')->form();
        $this->client->submit($form);

        $this->em->clear();
        $this->assertFalse($this->poolByCode('kantoor')->isArchived(), 'Poule weer actief.');
    }

    public function testDefaultPoolHasNoArchiveButton(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $default = $this->em->getRepository(Pool::class)->findOneBy(['isDefault' => true]);
        $crawler = $this->client->request('GET', '/admin/poules');

        $this->assertSame(
            0,
            $crawler->filter('form[action="/admin/poules/' . $default->getId() . '/archiveren"]')->count(),
            'De standaardpoule kan niet worden gearchiveerd.'
        );
    }

    public function testOrphanedUserSeesNotice(): void
    {
        // Maak Anne een wees: alleen lid van Kantoor, en archiveer Kantoor.
        $anne = $this->user('anne@trepiedi.test');
        $anne->removePool($this->poolByCode('algemeen'));
        $this->poolByCode('kantoor')->archive();
        $this->em->flush();

        $this->client->loginUser($this->user('anne@trepiedi.test'));
        $crawler = $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('niet meer aan een poule gekoppeld', $crawler->filter('body')->text());
    }

    public function testRegistrationBlockedWhenStashedCodeBecameInvalid(): void
    {
        // Geldige link aanklikken (uitgelogd) → code onthouden.
        $this->client->request('GET', '/poule/inschrijven/kantoor');
        $this->assertSame('kantoor', $this->client->getRequest()->getSession()->get(PoolEnroller::SESSION_KEY));

        // Poule wordt daarna gearchiveerd.
        $this->poolByCode('kantoor')->archive();
        $this->em->flush();

        // Registreren wordt geweigerd; er komt geen account bij.
        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->selectButton('Registreren')->form([
            'registration_form[displayName]' => 'Gijs',
            'registration_form[email]' => 'gijs@trepiedi.test',
            'registration_form[plainPassword]' => 'geheim123',
        ]);
        $this->client->submit($form);

        $this->em->clear();
        $this->assertNull(
            $this->em->getRepository(User::class)->findOneBy(['email' => 'gijs@trepiedi.test']),
            'Geen registratie bij een foutieve poule-link.'
        );
    }

    public function testRegistrationWithoutCodeStillJoinsDefault(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->selectButton('Registreren')->form([
            'registration_form[displayName]' => 'Hanne',
            'registration_form[email]' => 'hanne@trepiedi.test',
            'registration_form[plainPassword]' => 'geheim123',
        ]);
        $this->client->submit($form);

        $this->em->clear();
        $hanne = $this->user('hanne@trepiedi.test');
        $this->assertNotNull($hanne);
        $this->assertTrue($hanne->isInPool($this->poolByCode('algemeen')));
    }

    private function poolByCode(string $code): Pool
    {
        return $this->em->getRepository(Pool::class)->findOneBy(['code' => $code]);
    }
}
