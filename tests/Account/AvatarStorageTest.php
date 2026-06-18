<?php

namespace App\Tests\Account;

use App\Account\AvatarStorage;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class AvatarStorageTest extends TestCase
{
    public function testParseCrop(): void
    {
        $this->assertSame(['x' => 10, 'y' => 5, 'size' => 40], AvatarStorage::parseCrop('10,5,40'));
        $this->assertNull(AvatarStorage::parseCrop(null));
        $this->assertNull(AvatarStorage::parseCrop(''));
        $this->assertNull(AvatarStorage::parseCrop('10,5'), 'Te weinig delen.');
        $this->assertNull(AvatarStorage::parseCrop('10,5,0'), 'size < 1.');
        $this->assertSame(['x' => 0, 'y' => 0, 'size' => 40], AvatarStorage::parseCrop('-3,-1,40'), 'x/y geklemd naar 0.');
    }

    public function testStoreFromPathWithCropProducesSquareVariants(): void
    {
        $dir = sys_get_temp_dir() . '/avtest-' . uniqid();
        mkdir($dir);

        try {
            $storage = new AvatarStorage($dir, new Filesystem());

            // Niet-vierkante bron (100×60).
            $src = $dir . '/src.png';
            $img = imagecreatetruecolor(100, 60);
            imagepng($img, $src);
            imagedestroy($img);

            $user = (new User())->setSlug('tester');
            $storage->storeFromPath($user, $src, ['x' => 10, 'y' => 5, 'size' => 40]);

            $base = $user->getAvatar();
            $this->assertNotNull($base);
            $this->assertFileExists($dir . '/' . $base . '-sm.jpg');
            $this->assertFileExists($dir . '/' . $base . '-lg.jpg');
            $this->assertFileExists($dir . '/' . $base . '-orig.png', 'Het volledige origineel blijft bewaard.');

            // De sm-variant is vierkant (64×64).
            [$width, $height] = getimagesize($dir . '/' . $base . '-sm.jpg');
            $this->assertSame(64, $width);
            $this->assertSame(64, $height);
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }
}
