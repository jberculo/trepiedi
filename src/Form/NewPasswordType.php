<?php

namespace App\Form;

use App\Validator\PasswordPolicy;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Een nieuw wachtwoord met bevestiging: twee invoervelden die moeten overeenkomen,
 * met de gedeelde wachtwoord-eisen. Gebruikt bij "wachtwoord wijzigen" (verplicht)
 * en de beheer-reset (optioneel). Bij 'required' = false mag het veld leeg blijven.
 */
class NewPasswordType extends AbstractType
{
    public function getParent(): string
    {
        return RepeatedType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'type' => PasswordType::class,
            'mapped' => false,
            'required' => true,
            'invalid_message' => 'validation.password_mismatch',
            'first_options' => [
                'label' => 'account.new_password',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'second_options' => [
                'label' => 'account.new_password_repeat',
                'attr' => ['autocomplete' => 'new-password'],
            ],
        ]);

        // De constraints volgen de 'required'-optie, zodat een optioneel veld
        // (leeg toegestaan) en een verplicht veld dezelfde lengte-eis delen.
        $resolver->setNormalizer('first_options', function (Options $options, array $value): array {
            $value['constraints'] = PasswordPolicy::constraints(required: $options['required']);

            return $value;
        });
    }
}
