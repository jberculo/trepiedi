<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;

/**
 * Maakt een persoonlijke API-sleutel in het formaat {tokenId}.{secret}. Alleen het
 * id (lookup) en een hash van de volledige sleutel worden bewaard; de sleutel zelf
 * wordt eenmalig getoond. Deze service kan een meegestuurde sleutel ook weer
 * valideren en aan de juiste gebruiker koppelen.
 */
class ApiTokenService
{
    private const TOKEN_ID_LENGTH = 16; // hex-tekens (8 bytes)

    public function __construct(private UserRepository $users)
    {
    }

    /**
     * De gebruiker bij een meegestuurde sleutel, of null als die ongeldig is.
     */
    public function resolve(string $token): ?User
    {
        if ($token === '') {
            return null;
        }

        $tokenId = $this->tokenId($token);
        if ($tokenId === null) {
            return null;
        }

        $user = $this->users->findOneByApiTokenId($tokenId);
        if ($user === null || $user->getApiTokenHash() === null) {
            return null;
        }

        return hash_equals($user->getApiTokenHash(), $this->hash($token)) ? $user : null;
    }

    public function issue(User $user): string
    {
        $token = $this->generate();
        $user->setApiTokenId($this->tokenId($token))
            ->setApiTokenHash($this->hash($token));

        return $token;
    }

    private function generate(): string
    {
        do {
            $tokenId = bin2hex(random_bytes(self::TOKEN_ID_LENGTH / 2));
        } while ($this->users->findOneByApiTokenId($tokenId) !== null);

        return $tokenId . '.' . bin2hex(random_bytes(24));
    }

    /**
     * Het lookup-id uit een sleutel ({tokenId}.{secret}), of null bij een ongeldig
     * formaat.
     */
    private function tokenId(string $token): ?string
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || strlen($parts[0]) !== self::TOKEN_ID_LENGTH || $parts[1] === '') {
            return null;
        }

        return ctype_xdigit($parts[0]) ? strtolower($parts[0]) : null;
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
