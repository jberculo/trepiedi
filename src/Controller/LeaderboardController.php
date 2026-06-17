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

        // Extra klassementen uit dezelfde data, elk op een eigen veld gesorteerd
        // (aflopend; bij gelijke stand alfabetisch). Deze tellen ongewogen — alleen
        // het algemeen klassement past de rondemultiplier toe.
        $byScore = $this->sortedBy($entries, static fn (LeaderboardEntry $e): int => $e->scorePoints);
        $byWinners = $this->sortedBy($entries, static fn (LeaderboardEntry $e): int => $e->advanceCount);
        $byLantern = $this->sortedBy($entries, static fn (LeaderboardEntry $e): int => $e->lanternPoints);
        $byInconsistent = $this->sortedBy($entries, static fn (LeaderboardEntry $e): int => $e->inconsistentCount);

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

    /**
     * Sorteert een kopie van de entries aflopend op de waarde uit $value; bij een
     * gelijke stand alfabetisch op spelernaam. De invoer blijft ongewijzigd.
     *
     * @param list<LeaderboardEntry>      $entries
     * @param \Closure(LeaderboardEntry): int $value
     *
     * @return list<LeaderboardEntry>
     */
    private function sortedBy(array $entries, \Closure $value): array
    {
        usort($entries, static fn (LeaderboardEntry $a, LeaderboardEntry $b): int =>
            [$value($b), $a->user->getDisplayName()] <=> [$value($a), $b->user->getDisplayName()]);

        return $entries;
    }
}
