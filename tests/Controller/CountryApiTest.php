<?php

namespace App\Tests\Controller;

use App\Tests\FixturesWebTestCase;

class CountryApiTest extends FixturesWebTestCase
{
    public function testRequiresAdmin(): void
    {
        $this->client->request('GET', '/api/landen?q=ned');
        // Niet-ingelogd wordt naar login gestuurd.
        $this->assertResponseRedirects('/login');
    }

    public function testReturnsMatchingCountriesAsJson(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));

        $this->client->request('GET', '/api/landen?q=ned');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertNotEmpty($data);
        $this->assertSame('Nederland', $data[0]['name']);
        $this->assertSame('nl', $data[0]['code']);
    }

    public function testBlankQueryReturnsEmptyList(): void
    {
        $this->client->loginUser($this->user('admin@trepiedi.test'));

        $this->client->request('GET', '/api/landen?q=');

        $this->assertResponseIsSuccessful();
        $this->assertSame('[]', trim((string) $this->client->getResponse()->getContent()));
    }
}
