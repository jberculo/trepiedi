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

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        // Strikt: geen enkele bron mag 'unsafe-inline' toestaan.
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
    }
}
