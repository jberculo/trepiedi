<?php

namespace App\Api;

use App\Entity\User;

/**
 * Autorisatie-helpers voor API-operaties: vereist een (beheerders)gebruiker bij
 * de meegestuurde sleutel, anders een ApiException.
 */
trait AuthorizesApiUser
{
    private function requireUser(?User $user): User
    {
        if (!$user instanceof User) {
            throw new ApiException(ApiError::Unauthorized, 'Ontbrekende of ongeldige API-sleutel (header X-API-Key).');
        }

        return $user;
    }

    private function requireAdmin(?User $user): User
    {
        $user = $this->requireUser($user);
        if (!$user->isAdmin()) {
            throw new ApiException(ApiError::Forbidden, 'Alleen beheerders mogen dit.');
        }

        return $user;
    }
}
