<?php

namespace App\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * Zoekt de gebruiker bij de meegestuurde API-sleutel (header X-API-Key).
 */
class ApiKeyResolver
{
    public function __construct(private UserRepository $users)
    {
    }

    public function fromRequest(Request $request): ?User
    {
        return $this->fromKey((string) $request->headers->get('X-API-Key', ''));
    }

    public function fromKey(string $key): ?User
    {
        return $key !== '' ? $this->users->findOneByApiToken($key) : null;
    }
}
