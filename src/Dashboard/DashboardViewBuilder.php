<?php

namespace App\Dashboard;

use App\Entity\FootballMatch;
use App\Entity\Prediction;
use App\Entity\Round;
use App\Entity\User;
use App\Form\PredictionFormFactory;
use App\Repository\PredictionRepository;
use App\Round\RoundViewProjector;
use App\Scoring\PredictionScore;
use App\Scoring\ScoringService;
use Symfony\Component\Form\FormView;

final class DashboardViewBuilder
{
    public function __construct(
        private PredictionRepository $predictionRepository,
        private ScoringService $scoringService,
        private PredictionFormFactory $predictionForms,
        private RoundViewProjector $rounds,
    ) {
    }

    /**
     * @return list<array{round: Round, matches: list<array{match: FootballMatch, prediction: ?Prediction, locked: bool, awaitingResult: bool, score: ?PredictionScore, form: ?FormView}>}>
     */
    public function buildForUser(User $user): array
    {
        $predictions = $this->predictionRepository->findByUserIndexedByMatch($user);
        $rounds = $this->rounds->project(function (FootballMatch $match) use ($predictions): array {
            $prediction = $predictions[$match->getId()] ?? null;

            return [
                'match' => $match,
                'prediction' => $prediction,
                'locked' => $match->isLocked(),
                'awaitingResult' => $match->isAwaitingResult(),
                'score' => $prediction !== null ? $this->scoringService->scorePrediction($prediction) : null,
                'form' => $this->buildFormView($match, $prediction),
            ];
        });

        return array_map(static fn (array $group): array => [
            'round' => $group['round'],
            'matches' => $group['items'],
        ], $rounds);
    }

    private function buildFormView(FootballMatch $match, ?Prediction $prediction): ?FormView
    {
        if ($match->isLocked() || !$match->isActive()) {
            return null;
        }

        return $this->predictionForms->createView($match, $prediction);
    }
}
