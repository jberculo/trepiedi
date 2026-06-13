<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\FixturesWebTestCase;

class UserSlugTest extends FixturesWebTestCase
{
    public function testSlugCollisionGetsSuffix(): void
    {
        $repo = $this->em->getRepository(User::class);

        // 'anne' bestaat al (fixtures); een tweede "Anne" wordt 'anne-2'.
        $this->assertSame('anne-2', $repo->uniqueSlug('Anne'));
        // Een vrije naam houdt zijn gewone slug.
        $this->assertSame('eddie', $repo->uniqueSlug('Eddie'));
    }

    public function testUnknownSlugReturns404(): void
    {
        $this->client->request('GET', '/speler/bestaat-niet');
        $this->assertResponseStatusCodeSame(404);
    }
}
