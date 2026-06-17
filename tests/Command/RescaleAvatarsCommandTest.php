<?php

namespace App\Tests\Command;

use App\Tests\FixturesWebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class RescaleAvatarsCommandTest extends FixturesWebTestCase
{
    public function testRescalesLegacyAvatarIntoVariants(): void
    {
        $avatarDir = self::getContainer()->getParameter('kernel.project_dir') . '/public/uploads/avatars';

        // Oud schema: één los bestand met extensie.
        $legacy = 'anne-legacy.png';
        $legacyPath = $avatarDir . '/' . $legacy;
        $img = imagecreatetruecolor(30, 30);
        imagepng($img, $legacyPath);
        imagedestroy($img);

        $anne = $this->user('anne@trepiedi.test');
        $anne->setAvatar($legacy);
        $this->em->flush();

        $tester = new CommandTester((new Application(self::$kernel))->find('app:rescale-avatars'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $this->em->clear();
        $base = $this->user('anne@trepiedi.test')->getAvatar();
        $this->assertNotSame($legacy, $base, 'Avatar verwijst nu naar een nieuwe basisnaam.');
        $this->assertFileExists($avatarDir . '/' . $base . '-sm.jpg');
        $this->assertFileExists($avatarDir . '/' . $base . '-lg.jpg');
        $this->assertFileExists($avatarDir . '/' . $base . '-orig.png', 'Het origineel wordt bewaard, met echte extensie.');
        $this->assertFileDoesNotExist($legacyPath, 'Het oude losse bestand is verwijderd.');

        @unlink($avatarDir . '/' . $base . '-sm.jpg');
        @unlink($avatarDir . '/' . $base . '-lg.jpg');
        @unlink($avatarDir . '/' . $base . '-orig.png');
    }
}
