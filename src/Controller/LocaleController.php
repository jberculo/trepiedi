<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LocaleController extends AbstractController
{
    #[Route('/taal/{_locale}', name: 'app_locale', requirements: ['_locale' => 'nl|en'])]
    public function switch(string $_locale, Request $request, EntityManagerInterface $em): Response
    {
        $request->getSession()->set('_locale', $_locale);

        // Voor ingelogde gebruikers ook de profielvoorkeur bijwerken (blijft bewaard).
        $user = $this->getUser();
        if ($user instanceof User) {
            $user->setLocale($_locale);
            $em->flush();
        }

        $referer = $request->headers->get('referer');

        return $this->redirect($referer ?: $this->generateUrl('app_leaderboard'));
    }
}
