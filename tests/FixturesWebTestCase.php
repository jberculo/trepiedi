<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\FootballMatch;
use App\Entity\User;
use App\Repository\FootballMatchRepository;
use App\Security\ApiTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Basis voor functionele tests: verse test-database met fixtures.
 */
abstract class FixturesWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);

        DatabaseBootstrap::resetSchema($this->em);
        DatabaseBootstrap::seedFixtures($this->em, $container);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    protected function user(string $email): User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    /**
     * Controleert (met een verse lezing) dat het opgegeven wachtwoord geldig is
     * voor de gebruiker.
     */
    protected function assertPasswordIs(string $email, string $plainPassword): void
    {
        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $this->assertTrue(
            $hasher->isPasswordValid($user, $plainPassword),
            sprintf('Verwacht dat "%s" als wachtwoord geldt voor %s.', $plainPassword, $email)
        );
    }

    protected function lockedMatch(): FootballMatch
    {
        $match = $this->em->getRepository(FootballMatch::class)->findOneLocked();
        if ($match instanceof FootballMatch) {
            return $match;
        }

        $this->fail('Geen gestarte wedstrijd in de fixtures.');
    }

    protected function openMatch(): FootballMatch
    {
        $match = $this->em->getRepository(FootballMatch::class)->findOneOpen();
        if ($match instanceof FootballMatch) {
            return $match;
        }

        $this->fail('Geen open wedstrijd in de fixtures.');
    }

    protected function issueApiToken(User $user): string
    {
        $token = static::getContainer()->get(ApiTokenService::class)->issue($user);
        $this->em->flush();

        return $token;
    }

    /**
     * Schrijft een geldige PNG naar een tijdelijk bestand en geeft het pad terug.
     * (Bewust via GD gegenereerd; een hand-getypte base64-PNG kan een CRC-fout geven.)
     */
    protected function makeImageFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'avatar') . '.png';
        $img = imagecreatetruecolor(20, 20);
        imagefilledrectangle($img, 0, 0, 19, 19, imagecolorallocate($img, 90, 150, 90));
        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }
}
