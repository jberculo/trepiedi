<?php

namespace App\Command;

use App\Account\AvatarStorage;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Eenmalig: zet avatars die nog in het oude (één-bestand) schema staan om naar de
 * kleine varianten ({basis}-sm.jpg / {basis}-lg.jpg) en verwijdert het oude bestand.
 * Idempotent: gebruikers die al in het nieuwe schema zitten, worden overgeslagen.
 */
#[AsCommand(
    name: 'app:rescale-avatars',
    description: 'Herschaalt bestaande avatars naar de kleine varianten.',
)]
class RescaleAvatarsCommand extends Command
{
    public function __construct(
        private UserRepository $users,
        private AvatarStorage $avatars,
        private EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/public/uploads/avatars')]
        private string $avatarDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rescaled = 0;
        $skipped = 0;

        foreach ($this->users->findAll() as $user) {
            $old = $user->getAvatar();
            if ($old === null || $old === '') {
                continue;
            }

            // Alleen het oude schema heeft een bestand met exact deze naam op schijf.
            $oldPath = $this->avatarDir . '/' . $old;
            if (!is_file($oldPath)) {
                continue;
            }

            try {
                $this->avatars->storeFromPath($user, $oldPath);
            } catch (\Throwable $e) {
                // Eén onleesbaar bestand mag de rest van de migratie niet stoppen.
                $io->warning(sprintf('%s: overgeslagen (%s)', $user->getEmail(), $e->getMessage()));
                ++$skipped;
                continue;
            }

            @unlink($oldPath);
            ++$rescaled;
            $io->writeln(sprintf('%s: %s -> %s', $user->getEmail(), $old, $user->getAvatar()));
        }

        $this->em->flush();
        $io->success(sprintf('%d avatar(s) herschaald.', $rescaled));
        if ($skipped > 0) {
            $io->warning(sprintf('%d avatar(s) overgeslagen door een leesfout.', $skipped));
        }

        return Command::SUCCESS;
    }
}
