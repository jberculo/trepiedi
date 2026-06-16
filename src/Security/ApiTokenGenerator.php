<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;

/**
 * Genereert een unieke, persoonlijke API-sleutel en zorgt dat elke gebruiker er
 * een heeft (lazy aanmaken).
 */
class ApiTokenGenerator
{
    public function __construct(private UserRepository $users)
    {
    }

    public function generate(): string
    {
        do {
            $token = bin2hex(random_bytes(24)); // 48 hex-tekens
        } while ($this->users->findOneByApiToken($token) !== null);

        return $token;
    }

    /**
     * Zorgt dat de gebruiker een API-sleutel heeft (genereert er een als die er
     * nog niet is). Geeft true terug als er een nieuwe is gemaakt.
     */
    public function ensure(User $user): bool
    {
        if ($user->getApiToken() !== null) {
            return false;
        }

        $user->setApiToken($this->generate());

        return true;
    }
}
