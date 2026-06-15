<?php

namespace App\Controller;

use App\Entity\User;
use App\Pool\PoolEnroller;
use App\Repository\PoolRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PoolController extends AbstractController
{
    /**
     * Inschrijven via een uitnodigingscode. Ingelogd: meteen lid worden en de
     * poule activeren. Uitgelogd: code onthouden en naar registreren sturen
     * (na registratie/inloggen wordt de code verzilverd).
     */
    #[Route('/poule/inschrijven/{code}', name: 'app_pool_join')]
    public function join(string $code, Request $request, PoolRepository $pools, EntityManagerInterface $em): Response
    {
        $pool = $pools->findOneActiveByCode($code);
        if ($pool === null) {
            $this->addFlash('error', 'pool.not_found');

            return $this->redirectToRoute('app_leaderboard');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            // Onthoud de code en laat eerst registreren/inloggen.
            $request->getSession()->set(PoolEnroller::SESSION_KEY, $code);
            $this->addFlash('info', 'pool.login_to_join');

            return $this->redirectToRoute('app_register');
        }

        $user->addPool($pool);
        $user->setActivePool($pool);
        $em->flush();
        $this->addFlash('success', 'pool.joined');

        return $this->redirectToPoolReferer($request);
    }

    /**
     * Wisselen naar een andere poule waarvan je al lid bent (navbar-switcher).
     */
    #[Route('/poule/wissel/{code}', name: 'app_pool_switch')]
    public function switch(string $code, Request $request, PoolRepository $pools, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $pool = $pools->findOneActiveByCode($code);
        if ($pool === null || !$user->isInPool($pool)) {
            $this->addFlash('error', 'pool.not_member');

            return $this->redirectToRoute('app_leaderboard');
        }

        $user->setActivePool($pool);
        $em->flush();

        return $this->redirectToPoolReferer($request);
    }

    private function redirectToPoolReferer(Request $request): Response
    {
        $referer = $request->headers->get('referer');

        return $this->redirect($referer ?: $this->generateUrl('app_leaderboard'));
    }
}
