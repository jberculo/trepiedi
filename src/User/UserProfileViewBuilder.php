<?php

namespace App\User;

use App\Entity\FootballMatch;
use App\Entity\Prediction;
use App\Entity\Round;
use App\Entity\User;
use App\Repository\PredictionRepository;
use App\Round\RoundViewProjector;
use App\Scoring\PredictionScore;
use App\Scoring\ScoringService;

final class UserProfileViewBuilder
{
    public function __construct(
        private PredictionRepository $predictionRepository,
        private ScoringService $scoringService,
        private RoundViewProjector $rounds,
    ) {
    }

    /**
     * @return list<array{round: Round, items: list<array{match: FootballMatch, prediction: ?Prediction, visible: bool, score: ?PredictionScore}>}>
     */
    public function buildRounds(User $profile, bool $isSelf): array
    {
        $predictions = $this->predictionRepository->findByUserIndexedByMatch($profile);

        return $this->rounds->project(function (FootballMatch $match) use ($predictions, $isSelf): array {
            $prediction = $predictions[$match->getId()] ?? null;
            $visible = $isSelf || $match->isLocked();

            return [
                'match' => $match,
                'prediction' => $visible ? $prediction : null,
                'visible' => $visible,
                'score' => ($visible && $prediction !== null && $match->hasResult())
                    ? $this->scoringService->scorePrediction($prediction)
                    : null,
            ];
        });
    }
}
