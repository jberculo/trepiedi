<?php

namespace App\Tests\Scoring;

use App\Entity\FootballMatch;
use App\Entity\Prediction;
use App\Entity\Team;
use App\Repository\FootballMatchRepository;
use App\Repository\PredictionRepository;
use App\Repository\RoundRepository;
use App\Repository\UserRepository;
use App\Scoring\ScoringService;
use PHPUnit\Framework\TestCase;

class ScoringServiceTest extends TestCase
{
    private ScoringService $service;
    private Team $home;
    private Team $away;

    protected function setUp(): void
    {
        $this->service = new ScoringService(
            $this->createMock(PredictionRepository::class),
            $this->createMock(FootballMatchRepository::class),
            $this->createMock(RoundRepository::class),
            $this->createMock(UserRepository::class),
        );

        $this->home = (new Team())->setName('Thuis');
        $this->away = (new Team())->setName('Uit');
    }

    private function match(int $homeScore, int $awayScore, ?Team $advancing): FootballMatch
    {
        return (new FootballMatch())
            ->setHomeTeam($this->home)
            ->setAwayTeam($this->away)
            ->setHomeScore($homeScore)
            ->setAwayScore($awayScore)
            ->setAdvancingTeam($advancing)
            ->setFinished(true);
    }

    private function prediction(FootballMatch $match, int $home, int $away, ?Team $advancing): Prediction
    {
        return (new Prediction())
            ->setFootballMatch($match)
            ->setHomeScore($home)
            ->setAwayScore($away)
            ->setAdvancingTeam($advancing);
    }

    public function testExactResultAndCorrectAdvanceGivesMaximum(): void
    {
        $match = $this->match(2, 1, $this->home);
        $score = $this->service->scorePrediction($this->prediction($match, 2, 1, $this->home));

        $this->assertSame(1, $score->homeGoalsPoint);
        $this->assertSame(1, $score->awayGoalsPoint);
        $this->assertSame(1, $score->exactBonusPoint);
        $this->assertSame(3, $score->advancePoints);
        $this->assertSame(6, $score->total());
        $this->assertSame(6, ScoringService::MAX_RAW_PER_MATCH);
    }

    public function testOnlyHomeGoalsCorrectGivesOnePoint(): void
    {
        $match = $this->match(2, 1, $this->home);
        $score = $this->service->scorePrediction($this->prediction($match, 2, 3, $this->home));

        // 1 punt voor 'voor', geen bonus; wel 3 voor de winnaar.
        $this->assertSame(1, $score->homeGoalsPoint);
        $this->assertSame(0, $score->awayGoalsPoint);
        $this->assertSame(0, $score->exactBonusPoint);
        $this->assertSame(3, $score->advancePoints);
        $this->assertSame(4, $score->total());
    }

    public function testOnlyAwayGoalsCorrectGivesOnePoint(): void
    {
        $match = $this->match(2, 1, $this->home);
        $score = $this->service->scorePrediction($this->prediction($match, 5, 1, $this->away));

        $this->assertSame(0, $score->homeGoalsPoint);
        $this->assertSame(1, $score->awayGoalsPoint);
        $this->assertSame(0, $score->exactBonusPoint);
        $this->assertSame(0, $score->advancePoints);
        $this->assertSame(1, $score->total());
    }

    public function testExactResultButWrongAdvanceGivesThree(): void
    {
        $match = $this->match(2, 1, $this->home);
        $score = $this->service->scorePrediction($this->prediction($match, 2, 1, $this->away));

        $this->assertSame(3, $score->homeGoalsPoint + $score->awayGoalsPoint + $score->exactBonusPoint);
        $this->assertSame(0, $score->advancePoints);
        $this->assertSame(3, $score->total());
    }

    public function testCorrectAdvanceWithWrongScore(): void
    {
        $match = $this->match(0, 0, $this->away);
        // Gelijkspel na verlenging; uitploeg wint na strafschoppen.
        $score = $this->service->scorePrediction($this->prediction($match, 1, 1, $this->away));

        $this->assertSame(0, $score->homeGoalsPoint);
        $this->assertSame(0, $score->awayGoalsPoint);
        $this->assertSame(3, $score->advancePoints);
        $this->assertSame(3, $score->total());
    }

    public function testNothingCorrectGivesZero(): void
    {
        $match = $this->match(2, 1, $this->home);
        $score = $this->service->scorePrediction($this->prediction($match, 0, 0, $this->away));

        $this->assertSame(0, $score->total());
    }

    public function testUnfinishedMatchGivesZero(): void
    {
        $match = (new FootballMatch())
            ->setHomeTeam($this->home)
            ->setAwayTeam($this->away)
            ->setHomeScore(2)
            ->setAwayScore(1)
            ->setAdvancingTeam($this->home)
            ->setFinished(false);

        $score = $this->service->scorePrediction($this->prediction($match, 2, 1, $this->home));

        $this->assertSame(0, $score->total());
    }

    public function testNoAdvancingTeamSetGivesNoAdvancePoints(): void
    {
        $match = $this->match(2, 1, null);
        $score = $this->service->scorePrediction($this->prediction($match, 2, 1, $this->home));

        $this->assertSame(3, $score->total(), 'Wel exacte uitslag (3), geen winnaar-punten.');
    }
}
