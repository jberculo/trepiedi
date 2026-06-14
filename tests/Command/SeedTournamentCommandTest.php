<?php

namespace App\Tests\Command;

use App\Entity\FootballMatch;
use App\Entity\Round;
use App\Tests\FixturesWebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SeedTournamentCommandTest extends FixturesWebTestCase
{
    private function tester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('app:seed-tournament'));
    }

    public function testRefusesWithoutForceWhenRoundsExist(): void
    {
        // De fixtures bevatten al ronden.
        $exit = $this->tester()->execute([]);

        $this->assertSame(Command::FAILURE, $exit);
    }

    public function testSeedsFiveRoundsWithExpectedWeights(): void
    {
        $tester = $this->tester();
        $tester->execute(['--force' => true]);
        $tester->assertCommandIsSuccessful();

        $this->em->clear();
        $rounds = $this->em->getRepository(Round::class)->findBy([], ['sortOrder' => 'ASC']);
        $this->assertCount(5, $rounds);
        // Verdubbelend tot de halve finales; de finale + troostfinale tellen samen op 8.
        $this->assertSame(
            [1.0, 2.0, 4.0, 8.0, 8.0],
            array_map(static fn (Round $r): float => $r->getWeight(), $rounds),
        );

        $matches = $this->em->getRepository(FootballMatch::class)->findAll();
        $this->assertCount(32, $matches, '16 + 8 + 4 + 2 + 2 = 32 wedstrijden.');
        foreach ($matches as $match) {
            $this->assertFalse($match->isActive(), 'Geseede wedstrijden moeten inactief zijn.');
            $this->assertFalse($match->isFinished());
            $this->assertNull($match->getAdvancingSide());
        }

        // 16 wedstrijden in de eerste ronde (16e finales), 2 in de laatste (finale + troostfinale).
        $this->assertCount(16, $rounds[0]->getMatches());
        $this->assertSame('Finale & troostfinale', $rounds[4]->getName());
        $this->assertCount(2, $rounds[4]->getMatches());
    }
}
