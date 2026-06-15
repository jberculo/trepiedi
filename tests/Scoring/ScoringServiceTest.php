<?php

namespace App\Tests\Scoring;

use App\Entity\FootballMatch;
use App\Entity\Prediction;
use App\Repository\FootballMatchRepository;
use App\Repository\PredictionRepository;
use App\Repository\RoundRepository;
use App\Repository\UserRepository;
use App\Scoring\ScoringService;
use PHPUnit\Framework\TestCase;

class ScoringServiceTest extends TestCase
{
    private const HOME = FootballMatch::SIDE_HOME;
    private const AWAY = FootballMatch::SIDE_AWAY;

    private ScoringService $service;

    protected function setUp(): void
    {
        $this->service = new ScoringService(
            $this->createMock(PredictionRepository::class),
            $this->createMock(FootballMatchRepository::class),
            $this->createMock(RoundRepository::class),
            $this->createMock(UserRepository::class),
        );
    }

    private function match(int $homeScore, int $awayScore, ?string $advancingSide): FootballMatch
    {
        return (new FootballMatch())
            ->setHomeTeam('Thuis')
            ->setAwayTeam('Uit')
            ->setHomeScore($homeScore)
            ->setAwayScore($awayScore)
            ->setAdvancingSide($advancingSide)
            ->setFinished(true);
    }

    private function prediction(FootballMatch $match, int $home, int $away, ?string $advancingSide): Prediction
    {
        return (new Prediction())
            ->setFootballMatch($match)
            ->setHomeScore($home)
            ->setAwayScore($away)
            ->setAdvancingSide($advancingSide);
    }

    public function testExactResultAndCorrectAdvanceGivesMaximum(): void
    {
        $match = $this->match(2, 1, self::HOME);
        $score = $this->service->scorePrediction($this->prediction($match, 2, 1, self::HOME));

        $this->assertSame(1, $score->homeGoalsPoint);
        $this->assertSame(1, $score->awayGoalsPoint);
        $this->assertSame(1, $score->exactBonusPoint);
        $this->assertSame(3, $score->advancePoints);
        $this->assertSame(6, $score->total());
        $this->assertSame(6, ScoringService::MAX_RAW_PER_MATCH);
    }

    public function testOnlyHomeGoalsCorrectGivesOnePoint(): void
    {
        $match = $this->match(2, 1, self::HOME);
        $score = $this->service->scorePrediction($this->prediction($match, 2, 3, self::HOME));

        // 1 punt voor 'voor', geen bonus; wel 3 voor de winnaar.
        $this->assertSame(1, $score->homeGoalsPoint);
        $this->assertSame(0, $score->awayGoalsPoint);
        $this->assertSame(0, $score->exactBonusPoint);
        $this->assertSame(3, $score->advancePoints);
        $this->assertSame(4, $score->total());
    }

    public function testOnlyAwayGoalsCorrectGivesOnePoint(): void
    {
        $match = $this->match(2, 1, self::HOME);
        $score = $this->service->scorePrediction($this->prediction($match, 5, 1, self::AWAY));

        $this->assertSame(0, $score->homeGoalsPoint);
        $this->assertSame(1, $score->awayGoalsPoint);
        $this->assertSame(0, $score->exactBonusPoint);
        $this->assertSame(0, $score->advancePoints);
        $this->assertSame(1, $score->total());
    }

    public function testExactResultButWrongAdvanceGivesThree(): void
    {
        $match = $this->match(2, 1, self::HOME);
        $score = $this->service->scorePrediction($this->prediction($match, 2, 1, self::AWAY));

        $this->assertSame(3, $score->homeGoalsPoint + $score->awayGoalsPoint + $score->exactBonusPoint);
        $this->assertSame(0, $score->advancePoints);
        $this->assertSame(3, $score->total());
    }

    public function testCorrectAdvanceWithWrongScore(): void
    {
        $match = $this->match(0, 0, self::AWAY);
        // Gelijkspel na verlenging; uitploeg wint na strafschoppen.
        $score = $this->service->scorePrediction($this->prediction($match, 1, 1, self::AWAY));

        $this->assertSame(0, $score->homeGoalsPoint);
        $this->assertSame(0, $score->awayGoalsPoint);
        $this->assertSame(3, $score->advancePoints);
        $this->assertSame(3, $score->total());
    }

    public function testNothingCorrectGivesZero(): void
    {
        $match = $this->match(2, 1, self::HOME);
        $score = $this->service->scorePrediction($this->prediction($match, 0, 0, self::AWAY));

        $this->assertSame(0, $score->total());
    }

    public function testUnfinishedMatchGivesZero(): void
    {
        $match = (new FootballMatch())
            ->setHomeTeam('Thuis')
            ->setAwayTeam('Uit')
            ->setHomeScore(2)
            ->setAwayScore(1)
            ->setAdvancingSide(self::HOME)
            ->setFinished(false);

        $score = $this->service->scorePrediction($this->prediction($match, 2, 1, self::HOME));

        $this->assertSame(0, $score->total());
    }

    public function testNoAdvancingSideSetGivesNoAdvancePoints(): void
    {
        $match = $this->match(2, 1, null);
        $score = $this->service->scorePrediction($this->prediction($match, 2, 1, self::HOME));

        $this->assertSame(3, $score->total(), 'Wel exacte uitslag (3), geen winnaar-punten.');
    }

    // ── Ronde lantaarn (strafpunten) ──────────────────────────────────────────

    public function testLanternDrawPredictedButNotDraw(): void
    {
        // Gelijkspel voorspeld, werd winst thuis; winnaar wél goed → alleen +1.
        $match = $this->match(2, 1, self::HOME);
        $this->assertSame(1, $this->service->lanternPenalty($this->prediction($match, 1, 1, self::HOME)));
    }

    public function testLanternWinLossPredictedButDraw(): void
    {
        // Winst voorspeld (2–1), werd gelijkspel (1–1); winnaar (na strafschoppen) wél goed → +1.
        $match = $this->match(1, 1, self::HOME);
        $this->assertSame(1, $this->service->lanternPenalty($this->prediction($match, 2, 1, self::HOME)));
    }

    public function testLanternWinLossPredictedButDrawWithWrongWinner(): void
    {
        // Winst voorspeld, werd gelijkspel, én verkeerde ploeg doorgelaten → +1 +1 = 2.
        $match = $this->match(1, 1, self::HOME);
        $this->assertSame(2, $this->service->lanternPenalty($this->prediction($match, 2, 1, self::AWAY)));
    }

    public function testLanternReversedResult(): void
    {
        // 3–1 voorspeld, werd 1–2 (omgekeerd) + verkeerde winnaar → +2 +1 = 3 (maximum).
        $match = $this->match(1, 2, self::AWAY);
        $this->assertSame(3, $this->service->lanternPenalty($this->prediction($match, 3, 1, self::HOME)));
        $this->assertSame(3, ScoringService::MAX_LANTERN_PER_MATCH);
    }

    public function testLanternCorrectTendencyNoPenalty(): void
    {
        // Winst thuis voorspeld, werd winst thuis, winnaar goed → geen strafpunten.
        $match = $this->match(2, 0, self::HOME);
        $this->assertSame(0, $this->service->lanternPenalty($this->prediction($match, 1, 0, self::HOME)));
    }

    public function testLanternUnfinishedMatchGivesZero(): void
    {
        $match = (new FootballMatch())->setHomeTeam('A')->setAwayTeam('B');
        $this->assertSame(0, $this->service->lanternPenalty($this->prediction($match, 2, 1, self::HOME)));
    }
}
