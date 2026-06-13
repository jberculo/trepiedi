<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SmokeTest extends WebTestCase
{
    public function testLoginPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Inloggen');
    }

    public function testRegisterPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
    }

    public function testDashboardRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/voorspellen');

        $this->assertResponseRedirects('/login');
    }

    public function testHomepageShowsPublicLeaderboard(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        // De homepage is het klassement en is ook zonder login te bekijken.
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Klassement');
    }

    public function testAdminRequiresAdminRole(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');

        // Anoniem: doorverwijzing naar login.
        $this->assertResponseRedirects('/login');
    }
}
