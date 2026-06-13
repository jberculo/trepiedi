<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\FootballMatch;
use App\Entity\User;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
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

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $loader = new Loader();
        $loader->addFixture(new AppFixtures($container->get(UserPasswordHasherInterface::class)));
        (new ORMExecutor($this->em, new ORMPurger()))->execute($loader->getFixtures());
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
        foreach ($this->em->getRepository(FootballMatch::class)->findAll() as $match) {
            if ($match->isLocked()) {
                return $match;
            }
        }
        $this->fail('Geen gestarte wedstrijd in de fixtures.');
    }

    protected function openMatch(): FootballMatch
    {
        foreach ($this->em->getRepository(FootballMatch::class)->findAll() as $match) {
            if (!$match->isLocked()) {
                return $match;
            }
        }
        $this->fail('Geen open wedstrijd in de fixtures.');
    }
}
