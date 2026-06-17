<?php

namespace App\Tests\Controller;

use App\Entity\Pool;
use App\Entity\Prediction;
use App\Entity\User;
use App\Tests\FixturesWebTestCase;

class AdminParticipantCrudTest extends FixturesWebTestCase
{
    public function testRequiresAdmin(): void
    {
        $this->client->loginUser($this->user('anne@trepiedi.test'));
        $this->client->request('GET', '/admin/deelnemers');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testIndexListsParticipants(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $crawler = $this->client->request('GET', '/admin/deelnemers');

        $this->assertResponseIsSuccessful();
        $body = $crawler->filter('body')->text();
        foreach (['Anne', 'Bram', 'Chris', 'Diana'] as $name) {
            $this->assertStringContainsString($name, $body);
        }
    }

    public function testEditUpdatesNameRoleAndPools(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $bram = $this->user('bram@trepiedi.test');
        $algemeen = $this->poolByCode('algemeen');
        $kantoor = $this->poolByCode('kantoor');
        $this->assertFalse($bram->isInPool($kantoor));

        $crawler = $this->client->request('GET', '/admin/deelnemers/' . $bram->getId() . '/bewerken');
        $form = $crawler->selectButton('Opslaan')->form();
        $this->client->submit($form, [
            'user_admin[displayName]' => 'Bram Bok',
            'user_admin[isAdmin]' => '1',
            'user_admin[pools]' => [$algemeen->getId(), $kantoor->getId()],
        ]);
        $this->assertResponseRedirects('/admin/deelnemers');

        $this->em->clear();
        $bram = $this->user('bram@trepiedi.test');
        $this->assertSame('Bram Bok', $bram->getDisplayName());
        $this->assertTrue($bram->isAdmin(), 'Beheerrechten toegekend.');
        $this->assertTrue($bram->isInPool($this->poolByCode('kantoor')), 'Toegevoegd aan Kantoor.');
    }

    public function testSelfHasNoDeleteButton(): void
    {
        $admin = $this->user('admin@trepiedi.test');
        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/admin/deelnemers');

        $this->assertSame(
            0,
            $crawler->filter('form[action="/admin/deelnemers/' . $admin->getId() . '/verwijderen"]')->count(),
            'Een beheerder kan zichzelf niet verwijderen.'
        );
    }

    public function testDeleteParticipantRemovesUserAndPredictions(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $bram = $this->user('bram@trepiedi.test');
        $bramId = $bram->getId();
        $this->assertGreaterThan(0, $this->em->getRepository(Prediction::class)->count(['user' => $bramId]));

        $crawler = $this->client->request('GET', '/admin/deelnemers');
        $form = $crawler->filter('form[action="/admin/deelnemers/' . $bramId . '/verwijderen"]')->form();
        $this->client->submit($form);
        $this->assertResponseRedirects('/admin/deelnemers');

        $this->em->clear();
        $this->assertNull($this->em->getRepository(User::class)->find($bramId), 'Deelnemer verwijderd.');
        $this->assertSame(0, $this->em->getRepository(Prediction::class)->count(['user' => $bramId]), 'Voorspellingen mee verwijderd.');
    }

    public function testAdminCanUploadParticipantPhoto(): void
    {
        $avatarDir = self::getContainer()->getParameter('kernel.project_dir') . '/public/uploads/avatars';

        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $bram = $this->user('bram@trepiedi.test');
        $this->assertNull($bram->getAvatar());

        $crawler = $this->client->request('GET', '/admin/deelnemers/' . $bram->getId() . '/bewerken');
        $form = $crawler->selectButton('Opslaan')->form();
        $form['user_admin[displayName]'] = 'Bram';
        $form['user_admin[avatar]']->upload($this->makeImageFile());
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/deelnemers');

        $this->em->clear();
        $bram = $this->user('bram@trepiedi.test');
        $this->assertNotNull($bram->getAvatar(), 'Beheerder kon een foto opslaan voor de deelnemer.');

        $base = $bram->getAvatar();
        $this->assertFileExists($avatarDir . '/' . $base . '-sm.jpg');
        @unlink($avatarDir . '/' . $base . '-sm.jpg');
        @unlink($avatarDir . '/' . $base . '-lg.jpg');
        @unlink($avatarDir . '/' . $base . '-orig');
    }

    private function poolByCode(string $code): Pool
    {
        return $this->em->getRepository(Pool::class)->findOneBy(['code' => $code]);
    }
}
