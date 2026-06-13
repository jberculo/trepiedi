<?php

namespace App\Controller;

use App\Entity\FootballMatch;
use App\Repository\PredictionRepository;
use App\Scoring\ScoringService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MatchViewController extends AbstractController
{
    #[Route('/wedstrijd/{id}', name: 'app_match_view')]
    public function view(
        FootballMatch $match,
        PredictionRepository $predictionRepository,
        ScoringService $scoringService,
    ): Response {
        $locked = $match->isLocked();
        $predictions = $predictionRepository->findByMatch($match);

        // Vóór de aftrap blijven andermans voorspellingen verborgen; we tonen
        // alleen het aantal. Daarna pas de details en de consensus.
        $rows = [];
        $advanceHome = 0;
        $advanceAway = 0;
        $scoreCounts = [];

        if ($locked) {
            foreach ($predictions as $prediction) {
                $advancingId = $prediction->getAdvancingTeam()?->getId();
                if ($advancingId === $match->getHomeTeam()?->getId()) {
                    ++$advanceHome;
                } elseif ($advancingId === $match->getAwayTeam()?->getId()) {
                    ++$advanceAway;
                }

                $key = $prediction->getHomeScore() . '-' . $prediction->getAwayScore();
                $scoreCounts[$key] = ($scoreCounts[$key] ?? 0) + 1;

                $rows[] = [
                    'prediction' => $prediction,
                    'score' => $match->hasResult() ? $scoringService->scorePrediction($prediction) : null,
                ];
            }
            arsort($scoreCounts);
        }

        return $this->render('match/view.html.twig', [
            'match' => $match,
            'locked' => $locked,
            'predictionCount' => count($predictions),
            'rows' => $rows,
            'advanceHome' => $advanceHome,
            'advanceAway' => $advanceAway,
            'scoreCounts' => $scoreCounts,
        ]);
    }
}
