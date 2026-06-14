<?php

namespace App\Twig;

use App\Reference\Countries;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig-functie country_flag(): rendert een vlaggetje voor een (vrij ingevoerde)
 * landnaam via de flag-icons-CSS. Wordt de naam niet als deelnemend land herkend,
 * dan volgt een grijs vlaggetje met een vraagteken.
 */
class FlagExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('country_flag', $this->countryFlag(...), ['is_safe' => ['html']]),
        ];
    }

    public function countryFlag(?string $name): string
    {
        $title = htmlspecialchars((string) $name, ENT_QUOTES);
        $code = Countries::codeForName($name);

        if ($code === null) {
            return sprintf(
                '<span class="team-flag team-flag-unknown" title="%s" aria-hidden="true">?</span>',
                $title
            );
        }

        return sprintf(
            '<span class="fi fi-%s team-flag" title="%s"></span>',
            htmlspecialchars($code, ENT_QUOTES),
            $title
        );
    }
}
