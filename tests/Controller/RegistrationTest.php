<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\FixturesWebTestCase;

class RegistrationTest extends FixturesWebTestCase
{
    public function testRegistrationPageLoads(): void
    {
        $this->client->request('GET', '/register');
        $this->assertResponseIsSuccessful();
    }

    public function testUserCanRegisterAndIsLoggedIn(): void
    {
        $crawler = $this->client->request('GET', '/register');

        $form = $crawler->selectButton('Registreren')->form([
            'registration_form[displayName]' => 'Eddie',
            'registration_form[email]' => 'eddie@trepiedi.test',
            'registration_form[plainPassword]' => 'geheim123',
        ]);
        $this->client->submit($form);

        // Na registratie wordt de gebruiker ingelogd en doorgestuurd.
        $this->assertResponseRedirects();

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'eddie@trepiedi.test']);
        $this->assertNotNull($user);
        $this->assertSame('Eddie', $user->getDisplayName());

        // Ingelogd: het (beveiligde) dashboard is bereikbaar.
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mijn voorspellingen');
    }

    public function testRegistrationRequiresValidInput(): void
    {
        $crawler = $this->client->request('GET', '/register');

        $form = $crawler->selectButton('Registreren')->form([
            'registration_form[displayName]' => 'X',
            'registration_form[email]' => 'geen-email',
            'registration_form[plainPassword]' => '123',
        ]);
        $this->client->submit($form);

        // Ongeldige invoer: geen redirect, formulier wordt opnieuw getoond.
        $this->assertResponseIsUnprocessable();
        $this->assertNull($this->em->getRepository(User::class)->findOneBy(['displayName' => 'X']));
    }
}
