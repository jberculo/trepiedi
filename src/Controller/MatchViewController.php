<?php

namespace App\Controller;

use App\Entity\FootballMatch;
use App\Pool\PoolContext;
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
        PoolContext $poolContext,
    ): Response {
        $locked = $match->isLocked();

        // Alleen voorspellingen van leden van de actieve poule meetellen.
        $memberIds = $poolContext->getMemberIds();
        $allowed = $memberIds === null ? null : array_flip($memberIds);
        $predictions = array_values(array_filter(
            $predictionRepository->findByMatch($match),
            static fn ($p): bool => $allowed === null || isset($allowed[$p->getUser()->getId()]),
        ));

        // Vóór de aftrap blijven andermans voorspellingen verborgen; we tonen
        // alleen het aantal. Daarna pas de details en de consensus.
        $rows = [];
        $advanceHome = 0;
        $advanceAway = 0;
        $scoreCounts = [];

        if ($locked) {
            foreach ($predictions as $prediction) {
                if ($prediction->getAdvancingSide() === FootballMatch::SIDE_HOME) {
                    ++$advanceHome;
                } elseif ($prediction->getAdvancingSide() === FootballMatch::SIDE_AWAY) {
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

            // Met een uitslag: de voorspellingen op aflopend aantal punten tonen
            // (meeste eerst), bij gelijk aantal op spelernaam.
            if ($match->hasResult()) {
                usort($rows, static fn (array $a, array $b): int =>
                    [$b['score']->total(), $a['prediction']->getUser()->getDisplayName()]
                    <=> [$a['score']->total(), $b['prediction']->getUser()->getDisplayName()]);
            }
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
