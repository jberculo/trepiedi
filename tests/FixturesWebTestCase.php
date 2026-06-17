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
}
