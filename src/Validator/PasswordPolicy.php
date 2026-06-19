<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Gedeelde validatieregels voor een (nieuw) wachtwoord, zodat registratie en
 * "wachtwoord wijzigen" dezelfde eisen hanteren.
 */
final class PasswordPolicy
{
    /**
     * @return list<Constraint>
     */
    public static function constraints(bool $required = true): array
    {
        $constraints = [];

        // Een optioneel veld (bijv. de beheer-reset) blijft leeg toegestaan; de
        // lengte-eis slaat lege waarden vanzelf over.
        if ($required) {
            $constraints[] = new NotBlank(message: 'validation.enter_password');
        }

        $constraints[] = new Length(
            min: 8,
            minMessage: 'validation.password_min',
            max: 4096,
        );

        return $constraints;
    }
}
