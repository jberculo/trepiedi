<?php

namespace App\Controller;

use App\Pool\PoolContext;
use App\Repository\RoundRepository;
use App\Scoring\LeaderboardEntry;
use App\Scoring\RankingType;
use App\Scoring\ScoringService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LeaderboardController extends AbstractController
{
    #[Route('/', name: 'app_leaderboard', defaults: ['tab' => 'points'])]
    #[Route('/plattement', name: 'app_leaderboard_flat', defaults: ['tab' => 'flat'])]
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
            'maxRawPerMatch' => ScoringService::MAX_RAW_PER_MATCH,
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
     * @return array<string, array{entries: list<LeaderboardEntry>, intro: string, showArrow: bool, arrowKey: ?string, invertArrow: bool, rankField: ?string, max_now_kind: string, max_tour_kind: string, mode: string}>
     */
    private function rankings(array $entries): array
    {
        return [
            'points' => [
                'entries' => $entries,
                'intro' => 'lb.general_intro',
                'showArrow' => true,
                'arrowKey' => 'points',
                'invertArrow' => RankingType::Points->invertedMovement(),
                'rankField' => null,
                'max_now_kind' => 'points',
                'max_tour_kind' => 'points',
                'mode' => 'points',
            ],
            'flat' => [
                'entries' => $this->sortedByType($entries, RankingType::Flat),
                'intro' => 'lb.flat_intro',
                'showArrow' => true,
                'arrowKey' => 'flat',
                'invertArrow' => false,
                'rankField' => 'rawTotal',
                'max_now_kind' => 'raw-finished',
                'max_tour_kind' => 'raw-total',
                'mode' => 'flat',
            ],
            'score' => [
                'entries' => $this->sortedByType($entries, RankingType::Score),
                'intro' => 'lb.balls_intro',
                'showArrow' => true,
                'arrowKey' => 'score',
                'invertArrow' => RankingType::Score->invertedMovement(),
                'rankField' => 'scorePoints',
                'max_now_kind' => 'triple-finished',
                'max_tour_kind' => 'triple-total',
                'mode' => 'score',
            ],
            'winners' => [
                'entries' => $this->sortedByType($entries, RankingType::Winners),
                'intro' => 'lb.oracle_intro',
                'showArrow' => true,
                'arrowKey' => 'winners',
                'invertArrow' => RankingType::Winners->invertedMovement(),
                'rankField' => 'advanceCount',
                'max_now_kind' => 'finished',
                'max_tour_kind' => 'total',
                'mode' => 'winners',
            ],
            'lantern' => [
                'entries' => $this->sortedByType($entries, RankingType::Lantern),
                'intro' => 'lb.lantern_intro',
                'showArrow' => true,
                'arrowKey' => 'lantern',
                'invertArrow' => RankingType::Lantern->invertedMovement(),
                'rankField' => 'lanternPoints',
                'max_now_kind' => 'triple-finished',
                'max_tour_kind' => 'triple-total',
                'mode' => 'lantern',
            ],
            'inconsistent' => [
                'entries' => $this->sortedByType($entries, RankingType::Inconsistent),
                'intro' => 'lb.inconsistent_intro',
                'showArrow' => true,
                'arrowKey' => 'inconsistent',
                'invertArrow' => RankingType::Inconsistent->invertedMovement(),
                'rankField' => 'inconsistentCount',
                'max_now_kind' => 'finished',
                'max_tour_kind' => 'total',
                'mode' => 'inconsistent',
            ],
        ];
    }

    /**
     * @param list<LeaderboardEntry> $entries
     * @return list<LeaderboardEntry>
     */
    private function sortedByType(array $entries, RankingType $type): array
    {
        usort($entries, $type->compare(...));

        return $entries;
    }
}
