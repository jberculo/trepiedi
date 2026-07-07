<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\FootballMatchRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig-functie open_unpredicted_count(): het aantal nog te voorspellen wedstrijden
 * voor de ingelogde speler (0 voor bezoekers). Voedt de herinneringsbanner.
 */
class PredictionReminderExtension extends AbstractExtension
{
    public function __construct(
        private Security $security,
        private FootballMatchRepository $matches,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('open_unpredicted_count', $this->openUnpredictedCount(...)),
        ];
    }

    public function openUnpredictedCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 0;
        }

        return $this->matches->countOpenWithoutPredictionForUser($user, new \DateTimeImmutable());
    }
}
