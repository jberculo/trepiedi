<?php

namespace App\Account;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AvatarStorage
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/avatars')]
        private string $avatarDir,
        private Filesystem $filesystem,
    ) {
    }

    public function store(User $user, UploadedFile $file): void
    {
        $filename = $user->getSlug() . '-' . uniqid() . '.' . ($file->guessExtension() ?: 'bin');
        $file->move($this->avatarDir, $filename);

        $oldAvatar = $user->getAvatar();
        if ($oldAvatar !== null) {
            $oldPath = $this->avatarDir . '/' . $oldAvatar;
            if (is_file($oldPath)) {
                $this->filesystem->remove($oldPath);
            }
        }

        $user->setAvatar($filename);
    }
}
