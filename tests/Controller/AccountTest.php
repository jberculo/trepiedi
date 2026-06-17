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

        $this->client->loginUser($this->user('bram@trepiedi.test'));
        $crawler = $this->client->request('GET', '/account');
        $form = $crawler->selectButton('Opslaan')->form();
        $form['account[displayName]'] = 'Bram';
        $form['account[avatar]']->upload($this->makeImageFile());
        $this->client->submit($form);

        $this->assertResponseRedirects('/account');

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'bram@trepiedi.test']);
        $this->assertNotNull($user->getAvatar(), 'Avatar-bestandsnaam had opgeslagen moeten worden.');

        // Opgeslagen als kleine vierkante varianten per UI-maat + het bewaarde origineel.
        $base = $user->getAvatar();
        $this->assertFileExists($avatarDir . '/' . $base . '-sm.jpg');
        $this->assertFileExists($avatarDir . '/' . $base . '-lg.jpg');
        $this->assertFileExists($avatarDir . '/' . $base . '-orig', 'Het origineel wordt bewaard.');
        @unlink($avatarDir . '/' . $base . '-sm.jpg');
        @unlink($avatarDir . '/' . $base . '-lg.jpg');
        @unlink($avatarDir . '/' . $base . '-orig');
    }

    public function testNewAvatarReplacesAndRemovesTheOld(): void
    {
        $avatarDir = self::getContainer()->getParameter('kernel.project_dir') . '/public/uploads/avatars';

        $this->client->loginUser($this->user('bram@trepiedi.test'));

        // Eerste avatar.
        $first = $this->uploadAvatar();
        $firstLg = $avatarDir . '/' . $first . '-lg.jpg';
        $this->assertFileExists($firstLg);

        // Tweede upload moet de eerste vervangen én de oude varianten verwijderen.
        $second = $this->uploadAvatar();
        $this->assertNotSame($first, $second, 'Nieuwe avatar krijgt een eigen basisnaam.');
        $this->assertFileExists($avatarDir . '/' . $second . '-lg.jpg');
        $this->assertFileDoesNotExist($firstLg, 'De oude avatar-variant is opgeruimd.');
        $this->assertFileDoesNotExist($avatarDir . '/' . $first . '-orig', 'Het oude origineel is opgeruimd.');

        @unlink($avatarDir . '/' . $second . '-sm.jpg');
        @unlink($avatarDir . '/' . $second . '-lg.jpg');
        @unlink($avatarDir . '/' . $second . '-orig');
    }

    private function uploadAvatar(): string
    {
        $crawler = $this->client->request('GET', '/account');
        $form = $crawler->selectButton('Opslaan')->form();
        $form['account[displayName]'] = 'Bram';
        $form['account[avatar]']->upload($this->makeImageFile());
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
