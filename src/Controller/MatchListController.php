<?php

namespace App\Controller;

use App\Repository\FootballMatchRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MatchListController extends AbstractController
{
    /**
     * Publiek overzicht van alle wedstrijden (oudste eerst), met uitslag indien bekend.
     */
    #[Route('/wedstrijden', name: 'app_matches')]
    public function index(FootballMatchRepository $matches): Response
    {
        return $this->render('match/list.html.twig', [
            'matches' => $matches->findAllChronological(),
        ]);
    }
}
