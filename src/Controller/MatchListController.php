<?php

namespace App\Controller;

use App\Repository\FootballMatchRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MatchListController extends AbstractController
{
    /**
     * Publiek overzicht van alle wedstrijden, onderverdeeld per ronde (oudste ronde
     * eerst, en binnen een ronde oudste wedstrijd eerst), met uitslag indien bekend.
     */
    #[Route('/wedstrijden', name: 'app_matches')]
    public function index(FootballMatchRepository $matches): Response
    {
        // findAllChronological levert al op aftrap (oplopend); we bucketen per ronde.
        $groups = [];
        foreach ($matches->findAllChronological() as $match) {
            $round = $match->getRound();
            $key = $round->getId();
            if (!isset($groups[$key])) {
                $groups[$key] = ['round' => $round, 'matches' => []];
            }
            $groups[$key]['matches'][] = $match;
        }

        usort(
            $groups,
            static fn (array $a, array $b): int => $a['round']->getSortOrder() <=> $b['round']->getSortOrder()
        );

        return $this->render('match/list.html.twig', [
            'groups' => $groups,
        ]);
    }
}
