<?php

namespace App\Controller;

use App\Entity\User;
use App\Pool\PoolContext;
use App\Repository\PredictionRepository;
use App\Repository\RoundRepository;
use App\Scoring\LeaderboardEntry;
use App\Scoring\ScoringService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserViewController extends AbstractController
{
    #[Route('/speler/{slug}', name: 'app_user_view')]
    public function view(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        User $profile,
        RoundRepository $roundRepository,
        PredictionRepository $predictionRepository,
        ScoringService $scoringService,
        PoolContext $poolContext,
    ): Response {
        $viewer = $this->getUser();
        $isSelf = $viewer instanceof User && $viewer->getId() === $profile->getId();

        $predictions = $predictionRepository->findByUserIndexedByMatch($profile);

        $rounds = [];
        foreach ($roundRepository->findAllOrdered() as $round) {
            $items = [];
            foreach ($round->getMatches() as $match) {
                $prediction = $predictions[$match->getId()] ?? null;
                // Andermans voorspelling pas zichtbaar vanaf de aftrap; eigen profiel altijd.
                $visible = $isSelf || $match->isLocked();

                $items[] = [
                    'match' => $match,
                    'prediction' => $visible ? $prediction : null,
                    'visible' => $visible,
                    'score' => ($visible && $prediction !== null && $match->hasResult())
                        ? $scoringService->scorePrediction($prediction)
                        : null,
                ];
            }
            $rounds[] = ['round' => $round, 'items' => $items];
        }

        return $this->render('user/view.html.twig', [
            'profile' => $profile,
            'isSelf' => $isSelf,
            'entry' => $this->leaderboardEntryFor($scoringService, $profile, $poolContext->getMemberIds()),
            'rounds' => $rounds,
        ]);
    }

    /**
     * @param list<int>|null $memberIds
     */
    private function leaderboardEntryFor(ScoringService $scoringService, User $profile, ?array $memberIds): ?LeaderboardEntry
    {
        foreach ($scoringService->buildLeaderboard(null, $memberIds) as $entry) {
            if ($entry->user->getId() === $profile->getId()) {
                return $entry;
            }
        }

        return null;
    }
}
