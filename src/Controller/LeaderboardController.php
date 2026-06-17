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
        $memberIds = $poolContext->getMemberIds();
        $entries = $scoringService->leaderboardWithMovement($memberIds);
        $rankings = $this->rankings($entries);
        $activeRanking = $rankings[$tab] ?? $rankings['points'];
        $finishedPerRound = $scoringService->finishedCountPerRound();

        return $this->render('leaderboard/index.html.twig', [
            'activeTab' => $tab,
            'entries' => $entries,
            'rankings' => $rankings,
            'activeRanking' => $activeRanking,
            'rounds' => $roundRepository->findAllBySortOrder(),
            'finishedPerRound' => $finishedPerRound,
            'totalFinished' => array_sum($finishedPerRound),
            'totalMatches' => $scoringService->tournamentMatchCount(),
            'maxPossible' => $scoringService->maxAchievableTotal(),
            'maxTournament' => $scoringService->maxTournamentTotal(),
            'timeline' => $tab === 'animation' ? $scoringService->matchTimeline($memberIds) : null,
            'activePool' => $poolContext->getActivePool(),
        ]);
    }

    #[Route('/klassement', name: 'app_leaderboard_legacy')]
    public function legacy(): Response
    {
        return $this->redirectToRoute('app_leaderboard', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @param list<LeaderboardEntry> $entries
     * @return array<string, array{entries: list<LeaderboardEntry>, intro: string, showArrow: bool, arrowKey: ?string, rankField: ?string, max_now_kind: string, max_tour_kind: string, mode: string}>
     */
    private function rankings(array $entries): array
    {
        return [
            'points' => [
                'entries' => $entries,
                'intro' => 'lb.general_intro',
                'showArrow' => true,
                'arrowKey' => 'points',
                'rankField' => null,
                'max_now_kind' => 'points',
                'max_tour_kind' => 'points',
                'mode' => 'points',
            ],
            'score' => [
                'entries' => $this->sortedBy($entries, static fn (LeaderboardEntry $e): int => $e->scorePoints),
                'intro' => 'lb.balls_intro',
                'showArrow' => true,
                'arrowKey' => 'score',
                'rankField' => 'scorePoints',
                'max_now_kind' => 'triple-finished',
                'max_tour_kind' => 'triple-total',
                'mode' => 'score',
            ],
            'winners' => [
                'entries' => $this->sortedBy($entries, static fn (LeaderboardEntry $e): int => $e->advanceCount),
                'intro' => 'lb.oracle_intro',
                'showArrow' => true,
                'arrowKey' => 'winners',
                'rankField' => 'advanceCount',
                'max_now_kind' => 'finished',
                'max_tour_kind' => 'total',
                'mode' => 'winners',
            ],
            'lantern' => [
                'entries' => $this->sortedBy($entries, static fn (LeaderboardEntry $e): int => $e->lanternPoints),
                'intro' => 'lb.lantern_intro',
                'showArrow' => false,
                'arrowKey' => null,
                'rankField' => 'lanternPoints',
                'max_now_kind' => 'triple-finished',
                'max_tour_kind' => 'triple-total',
                'mode' => 'lantern',
            ],
            'inconsistent' => [
                'entries' => $this->sortedBy($entries, static fn (LeaderboardEntry $e): int => $e->inconsistentCount),
                'intro' => 'lb.inconsistent_intro',
                'showArrow' => false,
                'arrowKey' => null,
                'rankField' => 'inconsistentCount',
                'max_now_kind' => 'finished',
                'max_tour_kind' => 'total',
                'mode' => 'inconsistent',
            ],
        ];
    }

    /**
     * @param list<LeaderboardEntry> $entries
     * @param \Closure(LeaderboardEntry): int $value
     * @return list<LeaderboardEntry>
     */
    private function sortedBy(array $entries, \Closure $value): array
    {
        usort($entries, static fn (LeaderboardEntry $a, LeaderboardEntry $b): int =>
            [$value($b), $a->user->getDisplayName()] <=> [$value($a), $b->user->getDisplayName()]);

        return $entries;
    }
}
