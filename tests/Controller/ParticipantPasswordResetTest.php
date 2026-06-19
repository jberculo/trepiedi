<?php

namespace App\Tests\Controller;

use App\Tests\FixturesWebTestCase;

class ParticipantPasswordResetTest extends FixturesWebTestCase
{
    public function testAdminCanResetANonAdminPassword(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $anne = $this->user('anne@trepiedi.test');

        $crawler = $this->client->request('GET', '/admin/deelnemers/' . $anne->getId() . '/bewerken');
        $this->assertCount(
            1,
            $crawler->filter('input[name="user_admin[newPassword][first]"]'),
            'Beheerder ziet het wachtwoordveld bij een gewone gebruiker.'
        );

        $form = $crawler->selectButton('Opslaan')->form([
            'user_admin[newPassword][first]' => 'resetwachtwoord',
            'user_admin[newPassword][second]' => 'resetwachtwoord',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/deelnemers');
        $this->assertPasswordIs('anne@trepiedi.test', 'resetwachtwoord');
    }

    public function testPasswordFieldIsHiddenForAnAdminTarget(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $admin = $this->user('admin@trepiedi.test');

        $crawler = $this->client->request('GET', '/admin/deelnemers/' . $admin->getId() . '/bewerken');
        $this->assertCount(
            0,
            $crawler->filter('input[name="user_admin[newPassword][first]"]'),
            'Het wachtwoord van een beheerder (ook zichzelf) is niet te resetten.'
        );
    }

    public function testEditingWithoutPasswordLeavesItUnchanged(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $anne = $this->user('anne@trepiedi.test');

        $crawler = $this->client->request('GET', '/admin/deelnemers/' . $anne->getId() . '/bewerken');
        $form = $crawler->selectButton('Opslaan')->form([
            'user_admin[displayName]' => 'Anne (bijgewerkt)',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/deelnemers');
        $this->assertPasswordIs('anne@trepiedi.test', 'test');
    }

    public function testResetWithTooShortPasswordIsRejected(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));
        $anne = $this->user('anne@trepiedi.test');

        $crawler = $this->client->request('GET', '/admin/deelnemers/' . $anne->getId() . '/bewerken');
        $form = $crawler->selectButton('Opslaan')->form([
            'user_admin[newPassword][first]' => 'kort',
            'user_admin[newPassword][second]' => 'kort',
        ]);
        $crawler = $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('minstens 8 tekens', $crawler->filter('body')->text());
        $this->assertPasswordIs('anne@trepiedi.test', 'test');
    }
}
