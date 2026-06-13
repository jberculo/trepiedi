<?php

namespace App\Command;

use App\Entity\FootballMatch;
use App\Entity\Round;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Vult de vijf knock-outronden (16e finale t/m finale) met placeholder-wedstrijden.
 * Alles staat inactief: pas wanneer de echte ploegen zijn ingevoerd en de wedstrijd
 * wordt geactiveerd, kan er worden voorspeld. Het rondegewicht verdubbelt per ronde
 * (1, 2, 4, 8, 16) zodat elke ronde even zwaar meetelt.
 */
#[AsCommand(
    name: 'app:seed-tournament',
    description: 'Maakt de vijf knock-outronden met placeholder-wedstrijden (inactief).',
)]
class SeedTournamentCommand extends Command
{
    /**
     * @var list<array{name: string, weight: float, matches: int, prefix: string, kickoff: string}>
     */
    private const ROUNDS = [
        ['name' => '16e finales',   'weight' => 1.0,  'matches' => 16, 'prefix' => 'Ploeg',         'kickoff' => '2026-06-28 18:00'],
        ['name' => '8e finales',    'weight' => 2.0,  'matches' => 8,  'prefix' => 'Winnaar 16e',   'kickoff' => '2026-07-03 18:00'],
        ['name' => 'Kwartfinales',  'weight' => 4.0,  'matches' => 4,  'prefix' => 'Winnaar 8e',    'kickoff' => '2026-07-09 18:00'],
        ['name' => 'Halve finales', 'weight' => 8.0,  'matches' => 2,  'prefix' => 'Winnaar kwart', 'kickoff' => '2026-07-14 21:00'],
        ['name' => 'Finale',        'weight' => 16.0, 'matches' => 1,  'prefix' => 'Winnaar halve', 'kickoff' => '2026-07-19 17:00'],
    ];

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Bestaande ronden en wedstrijden eerst verwijderen.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $roundRepo = $this->em->getRepository(Round::class);
        $existing = $roundRepo->findAll();

        if ($existing !== [] && !$input->getOption('force')) {
            $io->error('Er bestaan al ronden. Gebruik --force om ze (en alle wedstrijden) te vervangen.');

            return Command::FAILURE;
        }

        if ($existing !== []) {
            foreach ($this->em->getRepository(FootballMatch::class)->findAll() as $match) {
                $this->em->remove($match);
            }
            $this->em->flush();
            foreach ($existing as $round) {
                $this->em->remove($round);
            }
            $this->em->flush();
        }

        $sortOrder = 1;
        $matchTotal = 0;
        foreach (self::ROUNDS as $config) {
            $round = (new Round())
                ->setName($config['name'])
                ->setSortOrder($sortOrder++)
                ->setWeight($config['weight']);
            $this->em->persist($round);

            $base = new \DateTimeImmutable($config['kickoff']);
            for ($k = 1; $k <= $config['matches']; ++$k) {
                $match = (new FootballMatch())
                    ->setRound($round)
                    ->setHomeTeam(sprintf('%s %d', $config['prefix'], 2 * $k - 1))
                    ->setAwayTeam(sprintf('%s %d', $config['prefix'], 2 * $k))
                    ->setKickoffAt($base->modify(sprintf('+%d hours', $k - 1)))
                    ->setActive(false);
                $this->em->persist($match);
                ++$matchTotal;
            }

            $io->writeln(sprintf('%s — %d wedstrijden (gewicht %s)', $config['name'], $config['matches'], $config['weight']));
        }

        $this->em->flush();

        $io->success(sprintf('%d ronden en %d wedstrijden aangemaakt (allemaal inactief).', count(self::ROUNDS), $matchTotal));

        return Command::SUCCESS;
    }
}
