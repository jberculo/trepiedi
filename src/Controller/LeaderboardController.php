<?php

namespace App\Controller;

use App\Pool\PoolContext;
use App\Repository\RoundRepository;
use App\Scoring\LeaderboardEntry;
use App\Scoring\ScoringService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LeaderboardController extends AbstractController
{
    #[Route('/', name: 'app_leaderboard', defaults: ['tab' => 'points'])]
    #[Route('/balletjestrui', name: 'app_leaderboard_score', defaults: ['tab' => 'score'])]
    #[Route('/glazen-bal', name: 'app_leaderboard_winners', defaults: ['tab' => 'winners'])]
    #[Route('/ronde-lantaarn', name: 'app_leaderboard_lantern', defaults: ['tab' => 'lantern'])]
    #[Route('/tegenstrijdig', name: 'app_leaderboard_inconsistent', defaults: ['tab' => 'inconsistent'])]
    #[Route('/animatie', name: 'app_leaderboard_animation', defaults: ['tab' => 'animation'])]
    public function index(string $tab, ScoringService $scoringService, RoundRepository $roundRepository, PoolContext $poolContext): Response
    {
        // Klassement scopen op de leden van de actieve poule.
        $memberIds = $poolContext->getMemberIds();
        $entries = $scoringService->leaderboardWithMovement($memberIds);

        // Drie klassementen uit dezelfde data. Balletjestrui en glazen bal tellen
        // ongewogen (zonder rondemultiplier); alleen het algemeen klassement weegt.
        $byScore = $entries;
        usort($byScore, static fn (LeaderboardEntry $a, LeaderboardEntry $b): int =>
            [$b->scorePoints, $a->user->getDisplayName()] <=> [$a->scorePoints, $b->user->getDisplayName()]);

        $byWinners = $entries;
        usort($byWinners, static fn (LeaderboardEntry $a, LeaderboardEntry $b): int =>
            [$b->advanceCount, $a->user->getDisplayName()] <=> [$a->advanceCount, $b->user->getDisplayName()]);

        // Ronde lantaarn: wie verzamelde de meeste strafpunten met slechte voorspellingen?
        $byLantern = $entries;
        usort($byLantern, static fn (LeaderboardEntry $a, LeaderboardEntry $b): int =>
            [$b->lanternPoints, $a->user->getDisplayName()] <=> [$a->lanternPoints, $b->user->getDisplayName()]);

        // Tegenstrijdig: wie liet het vaakst een andere ploeg doorgaan dan de voorspelde winnaar?
        $byInconsistent = $entries;
        usort($byInconsistent, static fn (LeaderboardEntry $a, LeaderboardEntry $b): int =>
            [$b->inconsistentCount, $a->user->getDisplayName()] <=> [$a->inconsistentCount, $b->user->getDisplayName()]);

        $finishedPerRound = $scoringService->finishedCountPerRound();

        return $this->render('leaderboard/index.html.twig', [
            'activeTab' => $tab,
            'entries' => $entries,
            'byScore' => $byScore,
            'byWinners' => $byWinners,
            'byLantern' => $byLantern,
            'byInconsistent' => $byInconsistent,
            'rounds' => $roundRepository->findBy([], ['sortOrder' => 'ASC']),
            'finishedPerRound' => $finishedPerRound,
            'totalFinished' => array_sum($finishedPerRound),
            'totalMatches' => $scoringService->tournamentMatchCount(),
            'maxPossible' => $scoringService->maxAchievableTotal(),
            'maxTournament' => $scoringService->maxTournamentTotal(),
            'timeline' => $tab === 'animation' ? $scoringService->matchTimeline($memberIds) : null,
            'activePool' => $poolContext->getActivePool(),
        ]);
    }

    /**
     * Het klassement staat nu op de homepage; oude /klassement-links blijven werken.
     */
    #[Route('/klassement', name: 'app_leaderboard_legacy')]
    public function legacy(): Response
    {
        return $this->redirectToRoute('app_leaderboard', [], Response::HTTP_MOVED_PERMANENTLY);
    }
}
