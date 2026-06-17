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

    public function testApiKeyCanBeCreatedAndIsOnlyShownOnce(): void
    {
        $this->client->loginUser($this->user('anne@trepiedi.test'));

        $crawler = $this->client->request('GET', '/account');
        $this->assertStringContainsString('Er is nog geen API-sleutel aangemaakt.', $crawler->filter('body')->text());

        $form = $crawler->filter('form[action="/account/api-sleutel"]')->form();
        $this->client->submit($form);
        $this->assertResponseRedirects('/account');

        $crawler = $this->client->followRedirect();
        $body = $crawler->filter('body')->text();
        $this->assertStringContainsString('Nieuwe API-sleutel gegenereerd.', $body);
        $this->assertStringContainsString('Hij wordt hierna niet meer volledig getoond.', $body);
        $this->assertMatchesRegularExpression(
            '/[a-f0-9]{16}\.[a-f0-9]{48}/',
            (string) $crawler->filter('input.font-monospace')->attr('value')
        );

        $crawler = $this->client->request('GET', '/account');
        $this->assertStringNotContainsString('Hij wordt hierna niet meer volledig getoond.', $crawler->filter('body')->text());

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'anne@trepiedi.test']);
        $this->assertNotNull($user->getApiTokenId());
        $this->assertNotNull($user->getApiTokenHash());
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

    public function testNewAvatarReplacesAndRemovesTheOld(): void
    {
        $avatarDir = self::getContainer()->getParameter('kernel.project_dir') . '/public/uploads/avatars';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        $this->client->loginUser($this->user('bram@trepiedi.test'));

        // Eerste avatar.
        $first = $this->uploadAvatar($png);
        $firstPath = $avatarDir . '/' . $first;
        $this->assertFileExists($firstPath);

        // Tweede upload moet de eerste vervangen én het oude bestand verwijderen.
        $second = $this->uploadAvatar($png);
        $this->assertNotSame($first, $second, 'Nieuwe avatar krijgt een eigen bestandsnaam.');
        $this->assertFileExists($avatarDir . '/' . $second);
        $this->assertFileDoesNotExist($firstPath, 'De oude avatar is opgeruimd.');

        @unlink($avatarDir . '/' . $second);
    }

    private function uploadAvatar(string $png): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'avatar') . '.png';
        file_put_contents($tmp, $png);

        $crawler = $this->client->request('GET', '/account');
        $form = $crawler->selectButton('Opslaan')->form();
        $form['account[displayName]'] = 'Bram';
        $form['account[avatar]']->upload($tmp);
        $this->client->submit($form);

        $this->em->clear();

        return $this->user('bram@trepiedi.test')->getAvatar();
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
