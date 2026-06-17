<?php

namespace App\Locale;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class LocaleManager
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function applyUserLocale(SessionInterface $session, User $user): void
    {
        $this->applySessionLocale($session, $user->getLocale());
    }

    public function switchLocale(SessionInterface $session, ?User $user, string $locale): void
    {
        $this->applySessionLocale($session, $locale);

        if ($user instanceof User) {
            $user->setLocale($locale);
            $this->em->flush();
        }
    }

    public function applySessionLocale(SessionInterface $session, string $locale): void
    {
        $session->set('_locale', $locale);
    }
}
