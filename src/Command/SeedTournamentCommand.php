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
 * Vult het knock-outschema van het WK 2026 (vanaf de 16e finales) met de échte
 * speeldata, -tijden en -volgorde. De ploegen zijn nog positionele placeholders
 * (bracket-codes: 2A = nr. 2 groep A, W73 = winnaar wedstrijd 73, 3ABCDF = nr. 3
 * uit een van die groepen). Alles staat inactief: pas wanneer de echte ploegen
 * zijn ingevoerd en de wedstrijd wordt geactiveerd, kan er worden voorspeld.
 *
 * Speeldata en aanvangstijden in Nederlandse tijd (CET/CEST), bron: ESPN.nl
 * (WK 2026 volledig speelschema).
 *
 * Rondegewicht: 1, 2, 4, 8 en — op verzoek — de finale + troostfinale samen op 8.
 */
#[AsCommand(
    name: 'app:seed-tournament',
    description: 'Vult het WK-knock-outschema (vanaf de 16e finales) met echte speeldata (inactief).',
)]
class SeedTournamentCommand extends Command
{
    /**
     * @var list<array{name: string, weight: float, matches: list<array{home: string, away: string, kickoff: string}>}>
     */
    private const ROUNDS = [
        ['name' => '16e finales', 'weight' => 1.0, 'matches' => [
            ['home' => '2A', 'away' => '2B', 'kickoff' => '2026-06-28 21:00'],
            ['home' => '1C', 'away' => '2F', 'kickoff' => '2026-06-29 19:00'],
            ['home' => '1E', 'away' => '3ABCDF', 'kickoff' => '2026-06-29 22:30'],
            ['home' => '1F', 'away' => '2C', 'kickoff' => '2026-06-30 03:00'],
            ['home' => '2E', 'away' => '2I', 'kickoff' => '2026-06-30 19:00'],
            ['home' => '1I', 'away' => '3CDFGH', 'kickoff' => '2026-06-30 23:00'],
            ['home' => '1A', 'away' => '3CEFHI', 'kickoff' => '2026-07-01 03:00'],
            ['home' => '1L', 'away' => '3EHIJK', 'kickoff' => '2026-07-01 18:00'],
            ['home' => '1G', 'away' => '3AEHIJ', 'kickoff' => '2026-07-01 22:00'],
            ['home' => '1D', 'away' => '3BEFIJ', 'kickoff' => '2026-07-02 02:00'],
            ['home' => '1H', 'away' => '2J', 'kickoff' => '2026-07-02 21:00'],
            ['home' => '2K', 'away' => '2L', 'kickoff' => '2026-07-03 01:00'],
            ['home' => '1B', 'away' => '3EFGIJ', 'kickoff' => '2026-07-03 05:00'],
            ['home' => '2D', 'away' => '2G', 'kickoff' => '2026-07-03 20:00'],
            ['home' => '1J', 'away' => '2H', 'kickoff' => '2026-07-04 00:00'],
            ['home' => '1K', 'away' => '3DEIJL', 'kickoff' => '2026-07-04 03:30'],
        ]],
        ['name' => 'Achtste finales', 'weight' => 2.0, 'matches' => [
            ['home' => 'W73', 'away' => 'W75', 'kickoff' => '2026-07-04 19:00'],
            ['home' => 'W74', 'away' => 'W77', 'kickoff' => '2026-07-04 23:00'],
            ['home' => 'W76', 'away' => 'W78', 'kickoff' => '2026-07-05 22:00'],
            ['home' => 'W79', 'away' => 'W80', 'kickoff' => '2026-07-06 02:00'],
            ['home' => 'W83', 'away' => 'W84', 'kickoff' => '2026-07-06 21:00'],
            ['home' => 'W81', 'away' => 'W82', 'kickoff' => '2026-07-07 02:00'],
            ['home' => 'W86', 'away' => 'W88', 'kickoff' => '2026-07-07 18:00'],
            ['home' => 'W85', 'away' => 'W87', 'kickoff' => '2026-07-07 22:00'],
        ]],
        ['name' => 'Kwartfinales', 'weight' => 4.0, 'matches' => [
            ['home' => 'W89', 'away' => 'W90', 'kickoff' => '2026-07-09 22:00'],
            ['home' => 'W93', 'away' => 'W94', 'kickoff' => '2026-07-10 21:00'],
            ['home' => 'W91', 'away' => 'W92', 'kickoff' => '2026-07-11 23:00'],
            ['home' => 'W95', 'away' => 'W96', 'kickoff' => '2026-07-12 03:00'],
        ]],
        ['name' => 'Halve finales', 'weight' => 8.0, 'matches' => [
            ['home' => 'W97', 'away' => 'W98', 'kickoff' => '2026-07-14 21:00'],
            ['home' => 'W99', 'away' => 'W100', 'kickoff' => '2026-07-15 21:00'],
        ]],
        ['name' => 'Finale & troostfinale', 'weight' => 8.0, 'matches' => [
            ['home' => 'RU101', 'away' => 'RU102', 'kickoff' => '2026-07-18 23:00'],
            ['home' => 'W101', 'away' => 'W102', 'kickoff' => '2026-07-19 21:00'],
        ]],
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

            foreach ($config['matches'] as $entry) {
                $match = (new FootballMatch())
                    ->setRound($round)
                    ->setHomeTeam($entry['home'])
                    ->setAwayTeam($entry['away'])
                    ->setKickoffAt(new \DateTimeImmutable($entry['kickoff']))
                    ->setActive(false);
                $this->em->persist($match);
                ++$matchTotal;
            }

            $io->writeln(sprintf('%s — %d wedstrijden (gewicht %s)', $config['name'], count($config['matches']), $config['weight']));
        }

        $this->em->flush();

        $io->success(sprintf('%d ronden en %d wedstrijden aangemaakt (allemaal inactief).', count(self::ROUNDS), $matchTotal));

        return Command::SUCCESS;
    }
}
