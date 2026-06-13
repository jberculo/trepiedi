<?php

namespace App\Controller\Admin;

use App\Repository\FootballMatchRepository;
use App\Repository\RoundRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('', name: 'admin_dashboard')]
    public function index(
        RoundRepository $rounds,
        FootballMatchRepository $matches,
        UserRepository $users,
    ): Response {
        return $this->render('admin/dashboard.html.twig', [
            'roundCount' => count($rounds->findAll()),
            'matchCount' => count($matches->findAll()),
            'finishedCount' => count($matches->findBy(['finished' => true])),
            'userCount' => count($users->findAll()),
        ]);
    }
}
