<?php

namespace App\Controller;

use App\Entity\User;
use App\Pool\PoolContext;
use App\Scoring\LeaderboardEntry;
use App\Scoring\ScoringService;
use App\User\UserProfileViewBuilder;
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
        ScoringService $scoringService,
        PoolContext $poolContext,
        UserProfileViewBuilder $profileView,
    ): Response {
        $viewer = $this->getUser();
        $isSelf = $viewer instanceof User && $viewer->getId() === $profile->getId();
        $leaderboard = $scoringService->buildLeaderboard(null, $poolContext->getMemberIds());

        return $this->render('user/view.html.twig', [
            'profile' => $profile,
            'isSelf' => $isSelf,
            'entry' => $this->entryFor($leaderboard, $profile),
            'rounds' => $profileView->buildRounds($profile, $isSelf),
        ]);
    }

    /**
     * @param list<LeaderboardEntry> $leaderboard
     */
    private function entryFor(array $leaderboard, User $profile): ?LeaderboardEntry
    {
        foreach ($leaderboard as $entry) {
            if ($entry->user->getId() === $profile->getId()) {
                return $entry;
            }
        }

        return null;
    }
}
