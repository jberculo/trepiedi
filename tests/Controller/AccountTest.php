<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\FixturesWebTestCase;

class AccountTest extends FixturesWebTestCase
{
    public function testAccountRequiresLogin(): void
    {
        $this->client->request('GET', '/account');
        $this->assertResponseRedirects('/login');
    }

    public function testAccountPageLoads(): void
    {
        $this->client->loginUser($this->user('anne@trepiedi.test'));
        $this->client->request('GET', '/account');
        $this->assertResponseIsSuccessful();
    }

    public function testAvatarUploadIsStored(): void
    {
        $avatarDir = self::getContainer()->getParameter('kernel.project_dir') . '/public/uploads/avatars';

        // Klein geldig PNG'tje (1×1) wegschrijven als upload.
        $tmp = tempnam(sys_get_temp_dir(), 'avatar') . '.png';
        file_put_contents($tmp, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ));

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/account');
        $form = $crawler->selectButton('Opslaan')->form();
        $form['account[displayName]'] = 'Bram';
        $form['account[avatar]']->upload($tmp);
        $this->client->submit($form);

        $this->assertResponseRedirects('/account');

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'bram@trepiedi.test']);
        $this->assertNotNull($user->getAvatar(), 'Avatar-bestandsnaam had opgeslagen moeten worden.');

        $stored = $avatarDir . '/' . $user->getAvatar();
        $this->assertFileExists($stored);
        @unlink($stored);
    }

    public function testNameChangeUpdatesSlug(): void
    {
        $this->client->loginUser($this->user('anne@trepiedi.test'));
        $crawler = $this->client->request('GET', '/account');

        $form = $crawler->selectButton('Opslaan')->form([
            'account[displayName]' => 'Annabel',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/account');

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'anne@trepiedi.test']);
        $this->assertSame('Annabel', $user->getDisplayName());
        $this->assertSame('annabel', $user->getSlug());
    }
}
