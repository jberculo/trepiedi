<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LocaleController extends AbstractController
{
    #[Route('/taal/{_locale}', name: 'app_locale', requirements: ['_locale' => 'nl|en'])]
    public function switch(string $_locale, Request $request): Response
    {
        $request->getSession()->set('_locale', $_locale);

        $referer = $request->headers->get('referer');

        return $this->redirect($referer ?: $this->generateUrl('app_leaderboard'));
    }
}
