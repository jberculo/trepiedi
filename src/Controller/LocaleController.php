<?php

namespace App\Controller;

use App\Entity\User;
use App\Locale\LocaleManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LocaleController extends AbstractController
{
    use SafeRedirectTrait;

    #[Route('/taal/{_locale}', name: 'app_locale', requirements: ['_locale' => 'nl|en'])]
    public function switch(string $_locale, Request $request, LocaleManager $localeManager): Response
    {
        $user = $this->getUser();
        $localeManager->switchLocale($request->getSession(), $user instanceof User ? $user : null, $_locale);

        return $this->redirect($this->safeRedirectTarget($request) ?? $this->generateUrl('app_leaderboard'));
    }
}
