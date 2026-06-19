<?php

namespace App\Tests\Controller;

use App\Tests\FixturesWebTestCase;

class ChangePasswordTest extends FixturesWebTestCase
{
    public function testUserChangesPasswordWithCorrectCurrent(): void
    {
        $this->client->loginUser($this->user('anne@trepiedi.test'));

        $crawler = $this->client->request('GET', '/account');
        $form = $crawler->selectButton('Wachtwoord wijzigen')->form([
            'change_password[currentPassword]' => 'test',
            'change_password[newPassword][first]' => 'nieuwwachtwoord',
            'change_password[newPassword][second]' => 'nieuwwachtwoord',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/account');
        $this->assertPasswordIs('anne@trepiedi.test', 'nieuwwachtwoord');
    }

    public function testWrongCurrentPasswordIsRejected(): void
    {
        $this->client->loginUser($this->user('anne@trepiedi.test'));

        $crawler = $this->client->request('GET', '/account');
        $form = $crawler->selectButton('Wachtwoord wijzigen')->form([
            'change_password[currentPassword]' => 'foutje',
            'change_password[newPassword][first]' => 'nieuwwachtwoord',
            'change_password[newPassword][second]' => 'nieuwwachtwoord',
        ]);
        $crawler = $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('Je huidige wachtwoord klopt niet.', $crawler->filter('body')->text());
        // Het wachtwoord is niet gewijzigd.
        $this->assertPasswordIs('anne@trepiedi.test', 'test');
    }

    public function testSuccessMessageIsShownAfterChange(): void
    {
        $this->client->loginUser($this->user('anne@trepiedi.test'));

        $crawler = $this->client->request('GET', '/account');
        $form = $crawler->selectButton('Wachtwoord wijzigen')->form([
            'change_password[currentPassword]' => 'test',
            'change_password[newPassword][first]' => 'nieuwwachtwoord',
            'change_password[newPassword][second]' => 'nieuwwachtwoord',
        ]);
        $this->client->submit($form);

        $crawler = $this->client->followRedirect();
        $this->assertStringContainsString('Je wachtwoord is gewijzigd.', $crawler->filter('body')->text());
    }

    public function testEmptyCurrentPasswordIsRejected(): void
    {
        $this->client->loginUser($this->user('anne@trepiedi.test'));

        $crawler = $this->client->request('GET', '/account');
        $form = $crawler->selectButton('Wachtwoord wijzigen')->form([
            'change_password[currentPassword]' => '',
            'change_password[newPassword][first]' => 'nieuwwachtwoord',
            'change_password[newPassword][second]' => 'nieuwwachtwoord',
        ]);
        $crawler = $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('Vul een wachtwoord in.', $crawler->filter('body')->text());
        $this->assertPasswordIs('anne@trepiedi.test', 'test');
    }

    public function testNewPasswordsMustMatch(): void
    {
        $this->client->loginUser($this->user('anne@trepiedi.test'));

        $crawler = $this->client->request('GET', '/account');
        $form = $crawler->selectButton('Wachtwoord wijzigen')->form([
            'change_password[currentPassword]' => 'test',
            'change_password[newPassword][first]' => 'nieuwwachtwoord',
            'change_password[newPassword][second]' => 'andersanders',
        ]);
        $crawler = $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('De twee wachtwoorden komen niet overeen.', $crawler->filter('body')->text());
        $this->assertPasswordIs('anne@trepiedi.test', 'test');
    }

    public function testNewPasswordTooShortIsRejected(): void
    {
        $this->client->loginUser($this->user('anne@trepiedi.test'));

        $crawler = $this->client->request('GET', '/account');
        $form = $crawler->selectButton('Wachtwoord wijzigen')->form([
            'change_password[currentPassword]' => 'test',
            'change_password[newPassword][first]' => 'kort',
            'change_password[newPassword][second]' => 'kort',
        ]);
        $crawler = $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('minstens 8 tekens', $crawler->filter('body')->text());
        $this->assertPasswordIs('anne@trepiedi.test', 'test');
    }

    public function testAdminMustAlsoEnterCurrentPasswordInProfile(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));

        $crawler = $this->client->request('GET', '/account');
        $this->assertCount(
            1,
            $crawler->filter('input[name="change_password[currentPassword]"]'),
            'Ook een beheerder vult op het eigen profiel het huidige wachtwoord in.'
        );

        $form = $crawler->selectButton('Wachtwoord wijzigen')->form([
            'change_password[currentPassword]' => 'admin',
            'change_password[newPassword][first]' => 'nieuwadminpw',
            'change_password[newPassword][second]' => 'nieuwadminpw',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/account');
        $this->assertPasswordIs('admin@trepiedi.test', 'nieuwadminpw');
    }

    public function testCurrentPasswordFieldIsAlwaysPresent(): void
    {
        $this->client->loginUser($this->user('anne@trepiedi.test'));

        $crawler = $this->client->request('GET', '/account');
        $this->assertCount(
            1,
            $crawler->filter('input[name="change_password[currentPassword]"]'),
            'Iedereen moet het huidige wachtwoord invullen.'
        );
    }
}
