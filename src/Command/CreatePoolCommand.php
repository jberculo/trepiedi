<?php

namespace App\Command;

use App\Entity\Pool;
use App\Repository\PoolRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Maakt een nieuwe poule met inschrijfcode aan en toont de uitnodigings-URL.
 */
#[AsCommand(
    name: 'app:create-pool',
    description: 'Maakt een nieuwe poule met inschrijfcode aan.',
)]
class CreatePoolCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private PoolRepository $pools,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Naam van de poule')
            ->addOption('code', null, InputOption::VALUE_REQUIRED, 'Eigen inschrijfcode (anders willekeurig)')
            ->addOption('default', null, InputOption::VALUE_NONE, 'Maak dit de standaardpoule');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = (string) $input->getArgument('name');
        $code = (string) ($input->getOption('code') ?: bin2hex(random_bytes(4)));
        $code = strtolower($code);

        if ($this->pools->findOneByCode($code) !== null) {
            $io->error(sprintf('Er bestaat al een poule met code "%s".', $code));

            return Command::FAILURE;
        }

        $pool = (new Pool())->setName($name)->setCode($code);

        if ($input->getOption('default')) {
            $current = $this->pools->findDefault();
            if ($current !== null) {
                $current->setDefault(false);
            }
            $pool->setDefault(true);
        }

        $this->em->persist($pool);
        $this->em->flush();

        $io->success(sprintf('Poule "%s" aangemaakt%s.', $name, $pool->isDefault() ? ' (standaard)' : ''));
        $io->writeln(sprintf('Inschrijf-URL: /poule/inschrijven/%s', $code));

        return Command::SUCCESS;
    }
}
