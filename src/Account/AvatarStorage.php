<?php

namespace App\Account;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Bewaart een avatar als kleine, vierkante JPG-varianten (één per UI-maat) plus het
 * volledige, ongesneden origineel als bron voor toekomstige formaten. `User::avatar`
 * houdt de basisnaam vast; de bestanden heten `{basis}-{maat}.jpg` en `{basis}-orig.{ext}`.
 * Bij een nieuwe upload worden alle bestanden van de oude basis verwijderd.
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

    /**
     * @param array{x: int, y: int, size: int}|null $crop Door de gebruiker gekozen vierkante uitsnede (bronpixels).
     */
    public function store(User $user, UploadedFile $file, ?array $crop = null): void
    {
        $this->storeFromPath($user, $file->getPathname(), $crop);
    }

    /**
     * Genereert de varianten uit een bestaand afbeeldingsbestand. Verwijdert de oude
     * varianten en zet de nieuwe basisnaam op de gebruiker.
     *
     * @param array{x: int, y: int, size: int}|null $crop
     */
    public function storeFromPath(User $user, string $sourcePath, ?array $crop = null): void
    {
        $source = $this->loadSquare($sourcePath, $crop);
        $base = $user->getSlug() . '-' . uniqid();

        foreach (self::SIZES as $name => $px) {
            $thumb = imagecreatetruecolor($px, $px);
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $px, $px, imagesx($source), imagesy($source));
            imagejpeg($thumb, $this->avatarDir . '/' . $base . '-' . $name . '.jpg', 85);
            imagedestroy($thumb);
        }
        imagedestroy($source);

        // Het volledige (ongesneden) origineel bewaren als bron voor toekomstige
        // formaten, met de echte extensie (afgeleid uit de bytes).
        copy($sourcePath, $this->avatarDir . '/' . $base . '-orig.' . $this->extensionFor($sourcePath));

        $this->remove($user->getAvatar());
        $user->setAvatar($base);
    }

    /**
     * Verwijdert alle bestanden van een avatar-basis (varianten én origineel,
     * ongeacht extensie).
     */
    public function remove(?string $base): void
    {
        if ($base === null || $base === '') {
            return;
        }

        foreach (glob($this->avatarDir . '/' . $base . '-*') ?: [] as $path) {
            if (is_file($path)) {
                $this->filesystem->remove($path);
            }
        }
    }

    /**
     * Bestandsextensie op basis van het werkelijke afbeeldingstype.
     */
    private function extensionFor(string $path): string
    {
        $info = @getimagesize($path);

        return $info !== false ? image_type_to_extension($info[2], false) : 'bin';
    }

    /**
     * Parseert "x,y,size" (bronpixels) naar een crop-array, of null bij ontbreken/ongeldig.
     *
     * @return array{x: int, y: int, size: int}|null
     */
    public static function parseCrop(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $parts = explode(',', $value);
        if (count($parts) !== 3) {
            return null;
        }

        $size = (int) $parts[2];
        if ($size < 1) {
            return null;
        }

        return ['x' => max(0, (int) $parts[0]), 'y' => max(0, (int) $parts[1]), 'size' => $size];
    }

    /**
     * Leest de upload in en snijdt 'm vierkant bij: op de gekozen uitsnede ($crop,
     * bronpixels) of anders vanuit het midden (cover).
     *
     * @param array{x: int, y: int, size: int}|null $crop
     */
    private function loadSquare(string $path, ?array $crop = null): \GdImage
    {
        $image = @imagecreatefromstring((string) file_get_contents($path));
        if ($image === false) {
            throw new \RuntimeException('Kon de afbeelding niet inlezen.');
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Gekozen uitsnede, geklemd binnen de afbeelding.
        if ($crop !== null) {
            $x = min($crop['x'], $width - 1);
            $y = min($crop['y'], $height - 1);
            $size = min($crop['size'], $width - $x, $height - $y);
            if ($size >= 1) {
                $square = imagecreatetruecolor($size, $size);
                imagecopy($square, $image, 0, 0, $x, $y, $size, $size);
                imagedestroy($image);

                return $square;
            }
        }

        // Standaard: vierkant vanuit het midden.
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
