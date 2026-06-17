<?php

namespace App\Tests\Scoring;

use App\DataFixtures\AppFixtures;
use App\Entity\Prediction;
use App\Repository\RoundRepository;
use App\Scoring\ScoringService;
use App\Tests\DatabaseBootstrap;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LeaderboardIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);

        DatabaseBootstrap::resetSchema($this->em);
        DatabaseBootstrap::seedFixtures($this->em, $container);
    }

    public function testDefaultWeightsMakeEveryMatchCountEqually(): void
    {
        $scoring = static::getContainer()->get(ScoringService::class);
        $rounds = static::getContainer()->get(RoundRepository::class);

        $achtste = $rounds->findOneBy(['name' => 'Achtste finales']);
        $kwart = $rounds->findOneBy(['name' => 'Kwartfinales']);

        $leaderboard = $scoring->buildLeaderboard();
        $this->assertNotEmpty($leaderboard);

        // Anne voorspelde alles perfect.
        $anne = $leaderboard[0];
        $this->assertSame('Anne', $anne->user->getDisplayName(), 'Perfecte speler staat bovenaan.');
        $this->assertSame(1, $anne->rank);

        // Ruwe punten: 8 wedstrijden × 6 + 4 wedstrijden × 6 = 72.
        $this->assertSame(72, $anne->rawTotal);

        // Met rondegewicht 1 is de gewogen score gelijk aan de ruwe punten;
        // elke wedstrijd telt even zwaar (6 punten).
        $this->assertEqualsWithDelta(48.0, $anne->roundWeighted($achtste->getId()), 0.001);
        $this->assertEqualsWithDelta(24.0, $anne->roundWeighted($kwart->getId()), 0.001);
        $this->assertEqualsWithDelta(72.0, $anne->weightedTotal, 0.001);

        // Maximaal haalbaar = 12 gespeelde wedstrijden × 6.
        $this->assertEqualsWithDelta(72.0, $scoring->maxAchievableTotal(), 0.001);
    }

    public function testRoundWeightScalesThatRound(): void
    {
        $rounds = static::getContainer()->get(RoundRepository::class);
        $achtste = $rounds->findOneBy(['name' => 'Achtste finales']);
        $kwart = $rounds->findOneBy(['name' => 'Kwartfinales']);
        $kwart->setWeight(2.0);
        $this->em->flush();

        $scoring = static::getContainer()->get(ScoringService::class);
        $anne = $this->entryFor($scoring->buildLeaderboard(), 'Anne');
        $this->assertNotNull($anne);

        // Kwartfinales tellen nu dubbel: 24 × 2 = 48; achtste blijft 48 (8 × 6 × 1).
        $this->assertEqualsWithDelta(48.0, $anne->roundWeighted($kwart->getId()), 0.001);
        $this->assertEqualsWithDelta(96.0, $anne->weightedTotal, 0.001);
        // Maximaal haalbaar: 8×6×1 + 4×6×2 = 48 + 48 = 96.
        $this->assertEqualsWithDelta(96.0, $scoring->maxAchievableTotal(), 0.001);

        // Met 8 vs 4 wedstrijden en gewicht 1 vs 2 tellen beide ronden weer even zwaar.
        $this->assertEqualsWithDelta(
            $anne->roundWeighted($achtste->getId()),
            $anne->roundWeighted($kwart->getId()),
            0.001
        );
    }

    public function testWeightAppliesToEveryPlayer(): void
    {
        $rounds = static::getContainer()->get(RoundRepository::class);
        $rounds->findOneBy(['name' => 'Kwartfinales'])->setWeight(3.0);
        $this->em->flush();

        $scoring = static::getContainer()->get(ScoringService::class);
        $leaderboard = $scoring->buildLeaderboard();

        // Bram: per wedstrijd 4 punten (1 thuis + 3 winnaar), 0 exact.
        $bram = $this->entryFor($leaderboard, 'Bram');
        $this->assertNotNull($bram);
        // Achtste 8×4×1 = 32, kwart 4×4×3 = 48 → 80.
        $this->assertSame(48, $bram->rawTotal);
        $this->assertEqualsWithDelta(80.0, $bram->weightedTotal, 0.001);
    }

    public function testPositionChangeSinceLastMatchday(): void
    {
        // Kwart ×2 zorgt dat Diana (vanaf kwart winnaars goed) Bram inhaalt.
        $rounds = static::getContainer()->get(RoundRepository::class);
        $rounds->findOneBy(['name' => 'Kwartfinales'])->setWeight(2.0);
        $this->em->flush();

        $scoring = static::getContainer()->get(ScoringService::class);
        $board = $scoring->leaderboardWithMovement();

        $diana = $this->entryFor($board, 'Diana');
        $bram = $this->entryFor($board, 'Bram');

        // Diana stijgt één plek in het algemeen klassement, Bram daalt één plek.
        $this->assertSame(1, $diana->change('points'));
        $this->assertSame(-1, $bram->change('points'));
    }

    public function testInconsistentCountsContradictoryPredictions(): void
    {
        $scoring = static::getContainer()->get(ScoringService::class);
        $board = $scoring->buildLeaderboard();

        // Anne voorspelt de echte uitslag + winnaar: nooit tegenstrijdig.
        $this->assertSame(0, $this->entryFor($board, 'Anne')->inconsistentCount);
        // Bram alleen bij FRA-SUI (voorspelt 1-2 → SUI wint, maar laat FRA doorgaan).
        $this->assertSame(1, $this->entryFor($board, 'Bram')->inconsistentCount);
        // Diana laat in de achtste de verliezer doorgaan: 5 beslissende achtste-duels.
        $this->assertSame(5, $this->entryFor($board, 'Diana')->inconsistentCount);
        // Chris laat altijd de verliezer doorgaan: alle 9 beslissende wedstrijden.
        $chris = $this->entryFor($board, 'Chris');
        $this->assertSame(9, $chris->inconsistentCount);

        // Chris staat dus bovenaan de tegenstrijdig-lijst.
        usort($board, static fn ($a, $b) => $b->inconsistentCount <=> $a->inconsistentCount);
        $this->assertSame('Chris', $board[0]->user->getDisplayName());
    }

    public function testRoundLanternScoresBadPredictions(): void
    {
        $scoring = static::getContainer()->get(ScoringService::class);
        $board = $scoring->buildLeaderboard();

        // Anne voorspelt elke uitslag exact goed: nooit straf.
        $this->assertSame(0, $this->entryFor($board, 'Anne')->lanternPoints);
        // Bram: 2× een gelijkspel voorspeld dat geen gelijkspel werd (+1 elk) en 3× winst/verlies
        // voorspeld bij een gelijkspel (FRA-SUI, ENG-JPN, ITA-CRO; +1 elk), winnaar steeds goed = 5.
        $this->assertSame(5, $this->entryFor($board, 'Bram')->lanternPoints);
        // Diana roept in de 8 achtste-duels de verkeerde winnaar uit (+1 elk), uitslag verder goed.
        $this->assertSame(8, $this->entryFor($board, 'Diana')->lanternPoints);
        // Chris roept in alle 12 wedstrijden de verkeerde winnaar uit (+1 elk).
        $chris = $this->entryFor($board, 'Chris');
        $this->assertSame(12, $chris->lanternPoints);

        // Chris staat dus bovenaan de ronde lantaarn.
        usort($board, static fn ($a, $b) => $b->lanternPoints <=> $a->lanternPoints);
        $this->assertSame('Chris', $board[0]->user->getDisplayName());
    }

    public function testReversedScorePredictionScoresTwoLanternPoints(): void
    {
        // Geef Anne bij één afgeronde wedstrijd een omgekeerde uitslag (winnaar blijft goed).
        $scoring = static::getContainer()->get(ScoringService::class);
        $match = null;
        foreach ($this->em->getRepository(Prediction::class)->findAll() as $prediction) {
            $m = $prediction->getFootballMatch();
            if ($prediction->getUser()->getDisplayName() === 'Anne'
                && $m->hasResult() && $m->getHomeScore() !== $m->getAwayScore()) {
                // Draai de score om t.o.v. de echte uitslag.
                $prediction->setHomeScore($m->getAwayScore());
                $prediction->setAwayScore($m->getHomeScore());
                $prediction->setAdvancingSide($m->getAdvancingSide());
                $this->assertSame(2, $scoring->lanternPenalty($prediction), 'Omgekeerde uitslag = 2 strafpunten.');
                return;
            }
        }

        $this->fail('Geen geschikte afgeronde wedstrijd gevonden.');
    }

    private function entryFor(array $leaderboard, string $displayName): ?object
    {
        foreach ($leaderboard as $entry) {
            if ($entry->user->getDisplayName() === $displayName) {
                return $entry;
            }
        }

        return null;
    }

    public function testNonParticipantsAreExcluded(): void
    {
        $scoring = static::getContainer()->get(ScoringService::class);
        $names = array_map(
            static fn ($entry) => $entry->user->getDisplayName(),
            $scoring->buildLeaderboard()
        );

        $this->assertContains('Anne', $names);
        $this->assertNotContains('Beheerder', $names, 'Account zonder voorspellingen hoort niet in het klassement.');
    }

    public function testLeaderboardIsSortedDescending(): void
    {
        $scoring = static::getContainer()->get(ScoringService::class);
        $leaderboard = $scoring->buildLeaderboard();

        $previous = null;
        foreach ($leaderboard as $entry) {
            if ($previous !== null) {
                $this->assertLessThanOrEqual($previous, $entry->weightedTotal, 'Aflopend gesorteerd.');
            }
            $previous = $entry->weightedTotal;
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
