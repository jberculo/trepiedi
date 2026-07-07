<?php

namespace App\Controller;

use App\Entity\User;
use App\Pool\PoolJoinManager;
use App\Repository\PoolRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PoolController extends AbstractController
{
    use SafeRedirectTrait;

    /**
     * Inschrijven via een uitnodigingscode. Ingelogd: meteen lid worden en de
     * poule activeren. Uitgelogd: code onthouden en naar registreren sturen
     * (na registratie/inloggen wordt de code verzilverd).
     */
    #[Route('/poule/inschrijven/{code}', name: 'app_pool_join', methods: ['GET', 'POST'])]
    public function join(
        string $code,
        Request $request,
        PoolRepository $pools,
        PoolJoinManager $poolJoins,
    ): Response {
        $pool = $pools->findOneActiveByCode($code);
        if ($pool === null) {
            $this->addFlash('error', 'pool.not_found');

            return $this->redirectToRoute('app_leaderboard');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            // Onthoud de code en laat eerst registreren/inloggen.
            $poolJoins->rememberCode($request->getSession(), $code);
            $this->addFlash('info', 'pool.login_to_join');

            return $this->redirectToRoute('app_register');
        }

        if (!$request->isMethod('POST')) {
            return $this->render('pool/join.html.twig', [
                'pool' => $pool,
                'back_path' => $this->safeRedirectTarget($request) ?? $this->generateUrl('app_leaderboard'),
            ]);
        }

        if (!$this->isCsrfTokenValid('join-pool-' . $pool->getCode(), (string) $request->getPayload()->get('_token'))) {
            throw $this->createAccessDeniedException('Ongeldige CSRF-token.');
        }

        $poolJoins->join($user, $code);
        $this->addFlash('success', 'pool.joined');

        return $this->redirectToPoolReferer($request);
    }

    /**
     * Wisselen naar een andere poule waarvan je al lid bent (navbar-switcher).
     */
    #[Route('/poule/wissel/{code}', name: 'app_pool_switch', methods: ['POST'])]
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

        if (!$this->isCsrfTokenValid('switch-pool-' . $pool->getCode(), (string) $request->getPayload()->get('_token'))) {
            throw $this->createAccessDeniedException('Ongeldige CSRF-token.');
        }

        $user->setActivePool($pool);
        $em->flush();

        return $this->redirectToPoolReferer($request);
    }

    private function redirectToPoolReferer(Request $request): Response
    {
        $referer = $this->safeRedirectTarget($request);

        return $this->redirect($referer ?: $this->generateUrl('app_leaderboard'));
    }
}
