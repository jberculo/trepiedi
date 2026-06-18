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

    public function testSkipsUnreadableAvatarAndContinues(): void
    {
        $avatarDir = self::getContainer()->getParameter('kernel.project_dir') . '/public/uploads/avatars';

        // Bram: een onleesbaar "bestand" (geen geldige afbeelding).
        $bad = 'bram-bad.png';
        file_put_contents($avatarDir . '/' . $bad, 'dit-is-geen-afbeelding');
        $this->user('bram@trepiedi.test')->setAvatar($bad);

        // Anne: een geldig oud bestand.
        $good = 'anne-legacy.png';
        $img = imagecreatetruecolor(20, 20);
        imagepng($img, $avatarDir . '/' . $good);
        imagedestroy($img);
        $this->user('anne@trepiedi.test')->setAvatar($good);
        $this->em->flush();

        $tester = new CommandTester((new Application(self::$kernel))->find('app:rescale-avatars'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $this->em->clear();
        // Bram is overgeslagen: avatar en bestand ongewijzigd.
        $this->assertSame($bad, $this->user('bram@trepiedi.test')->getAvatar(), 'Onleesbare avatar overgeslagen.');
        $this->assertFileExists($avatarDir . '/' . $bad);
        // Anne is wél gemigreerd.
        $anneBase = $this->user('anne@trepiedi.test')->getAvatar();
        $this->assertNotSame($good, $anneBase, 'Geldige avatar gaat gewoon door.');
        $this->assertFileExists($avatarDir . '/' . $anneBase . '-sm.jpg');

        @unlink($avatarDir . '/' . $bad);
        @unlink($avatarDir . '/' . $anneBase . '-sm.jpg');
        @unlink($avatarDir . '/' . $anneBase . '-lg.jpg');
        @unlink($avatarDir . '/' . $anneBase . '-orig.png');
    }
}
