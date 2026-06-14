<?php

namespace App\Tests\Controller;

use App\Tests\FixturesWebTestCase;

class SecurityHeadersTest extends FixturesWebTestCase
{
    public function testHardeningHeadersArePresent(): void
    {
        $this->client->request('GET', '/');
        $response = $this->client->getResponse();

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertNotNull($response->headers->get('Permissions-Policy'));
    }
}
