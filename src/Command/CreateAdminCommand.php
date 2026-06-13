<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Maakt een beheerder (ROLE_ADMIN) aan of promoveert een bestaande gebruiker.',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-mailadres van de beheerder')
            ->addArgument('password', InputArgument::OPTIONAL, 'Wachtwoord (verplicht bij een nieuw account; weglaten laat het bestaande wachtwoord ongewijzigd)')
            ->addArgument('displayName', InputArgument::OPTIONAL, 'Weergavenaam', 'Beheerder');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $password = $input->getArgument('password');
        $displayName = (string) $input->getArgument('displayName');

        $user = $this->users->findOneBy(['email' => $email]);
        $isNew = $user === null;

        if ($isNew && ($password === null || $password === '')) {
            $io->error('Een nieuw account vereist een wachtwoord.');

            return Command::INVALID;
        }

        if ($isNew) {
            $user = (new User())->setEmail($email)->setDisplayName($displayName);
            $user->setSlug($this->users->uniqueSlug($displayName));
        }

        // Rol toevoegen i.p.v. bestaande rollen overschrijven.
        $user->setRoles(array_values(array_unique([...$user->getRoles(), 'ROLE_ADMIN'])));

        // Wachtwoord alleen (her)zetten als er een is opgegeven.
        if ($password !== null && $password !== '') {
            $user->setPassword($this->hasher->hashPassword($user, (string) $password));
        }

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf(
            '%s beheerder "%s" (%s). Deze gebruiker kan wedstrijden, uitslagen, teams en ronden beheren.',
            $isNew ? 'Aangemaakte' : 'Bijgewerkte',
            $displayName,
            $email,
        ));

        return Command::SUCCESS;
    }
}
