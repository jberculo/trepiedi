<?php

namespace App\Account;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Bewaart een avatar als kleine, vierkante JPG-varianten — één per maat die de UI
 * gebruikt. De volledige upload wordt niet bewaard. `User::avatar` houdt de basisnaam
 * vast; de bestanden heten `{basis}-{maat}.jpg`. Bij een nieuwe upload worden de
 * oude varianten verwijderd.
 */
final class AvatarStorage
{
    /** Zijde (px) per UI-maat; ~2× de CSS-grootte voor scherpte (sm 28px, lg 96px). */
    public const SIZES = ['sm' => 64, 'lg' => 192];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/avatars')]
        private string $avatarDir,
        private Filesystem $filesystem,
    ) {
    }

    public function store(User $user, UploadedFile $file): void
    {
        $this->storeFromPath($user, $file->getPathname());
    }

    /**
     * Genereert de varianten uit een bestaand afbeeldingsbestand. Verwijdert de oude
     * varianten en zet de nieuwe basisnaam op de gebruiker.
     */
    public function storeFromPath(User $user, string $sourcePath): void
    {
        $source = $this->loadSquare($sourcePath);
        $base = $user->getSlug() . '-' . uniqid();

        foreach (self::SIZES as $name => $px) {
            $thumb = imagecreatetruecolor($px, $px);
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $px, $px, imagesx($source), imagesy($source));
            imagejpeg($thumb, $this->avatarDir . '/' . $base . '-' . $name . '.jpg', 85);
            imagedestroy($thumb);
        }
        imagedestroy($source);

        $this->remove($user->getAvatar());
        $user->setAvatar($base);
    }

    /**
     * Verwijdert alle maat-varianten van een avatar-basis.
     */
    public function remove(?string $base): void
    {
        if ($base === null || $base === '') {
            return;
        }

        foreach (array_keys(self::SIZES) as $name) {
            $path = $this->avatarDir . '/' . $base . '-' . $name . '.jpg';
            if (is_file($path)) {
                $this->filesystem->remove($path);
            }
        }
    }

    /**
     * Leest de upload in en snijdt 'm vierkant bij vanuit het midden (cover).
     */
    private function loadSquare(string $path): \GdImage
    {
        $image = @imagecreatefromstring((string) file_get_contents($path));
        if ($image === false) {
            throw new \RuntimeException('Kon de afbeelding niet inlezen.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);
        if ($width === $height) {
            return $image;
        }

        $square = imagecreatetruecolor($side, $side);
        imagecopy($square, $image, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), $side, $side);
        imagedestroy($image);

        return $square;
    }
}
