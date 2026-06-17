<?php

namespace App\Tests\Api;

use App\Api\ApiError;
use App\Api\ApiException;
use App\Api\AuthorizesApiUser;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * De autorisatie-helpers gooien de juiste domeinfout (ApiError) los van transport.
 */
class AuthorizesApiUserTest extends TestCase
{
    /** @var object{user: callable, admin: callable} */
    private object $sut;

    protected function setUp(): void
    {
        // Anonieme klasse die de trait gebruikt en de private helpers publiek maakt.
        $this->sut = new class {
            use AuthorizesApiUser;

            public function user(?User $u): User
            {
                return $this->requireUser($u);
            }

            public function admin(?User $u): User
            {
                return $this->requireAdmin($u);
            }
        };
    }

    public function testRequireUserThrowsUnauthorizedWithoutUser(): void
    {
        try {
            $this->sut->user(null);
            $this->fail('Verwachtte een ApiException.');
        } catch (ApiException $e) {
            $this->assertSame(ApiError::Unauthorized, $e->error);
        }
    }

    public function testRequireUserReturnsTheUser(): void
    {
        $user = $this->createStub(User::class);
        $this->assertSame($user, $this->sut->user($user));
    }

    public function testRequireAdminThrowsUnauthorizedWithoutUser(): void
    {
        $this->expectException(ApiException::class);
        try {
            $this->sut->admin(null);
        } catch (ApiException $e) {
            $this->assertSame(ApiError::Unauthorized, $e->error);
            throw $e;
        }
    }

    public function testRequireAdminThrowsForbiddenForNonAdmin(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isAdmin')->willReturn(false);

        try {
            $this->sut->admin($user);
            $this->fail('Verwachtte een ApiException.');
        } catch (ApiException $e) {
            $this->assertSame(ApiError::Forbidden, $e->error);
        }
    }

    public function testRequireAdminReturnsAdminUser(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isAdmin')->willReturn(true);

        $this->assertSame($user, $this->sut->admin($user));
    }
}
