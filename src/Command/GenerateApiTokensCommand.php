<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Security\ApiTokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Geeft elke gebruiker die er nog geen heeft een persoonlijke API-sleutel.
 */
#[AsCommand(
    name: 'app:generate-api-tokens',
    description: 'Genereert ontbrekende persoonlijke API-sleutels voor alle gebruikers.',
)]
class GenerateApiTokensCommand extends Command
{
    public function __construct(
        private UserRepository $users,
        private ApiTokenGenerator $generator,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $created = 0;
        foreach ($this->users->findAll() as $user) {
            if ($this->generator->ensure($user)) {
                ++$created;
            }
        }
        $this->em->flush();

        $io->success(sprintf('%d nieuwe API-sleutel(s) gegenereerd.', $created));

        return Command::SUCCESS;
    }
}
