<?php

namespace App\Tests\Command;

use App\Entity\User;
use App\Tests\FixturesWebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CreateAdminCommandTest extends FixturesWebTestCase
{
    private function tester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('app:create-admin'));
    }

    public function testCreatesNewAdmin(): void
    {
        $tester = $this->tester();
        $tester->execute([
            'email' => 'nieuw@trepiedi.test',
            'password' => 'pw123456',
            'displayName' => 'Nieuw',
        ]);
        $tester->assertCommandIsSuccessful();

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'nieuw@trepiedi.test']);
        $this->assertNotNull($user);
        $this->assertTrue($user->isAdmin());
        $this->assertSame('Nieuw', $user->getDisplayName());
    }

    public function testPromotesExistingUserAndKeepsRoleUser(): void
    {
        $tester = $this->tester();
        // Geen wachtwoord: bestaande gebruiker promoveren met behoud van wachtwoord.
        $tester->execute(['email' => 'anne@trepiedi.test']);
        $tester->assertCommandIsSuccessful();

        $this->em->clear();
        $anne = $this->em->getRepository(User::class)->findOneBy(['email' => 'anne@trepiedi.test']);
        $this->assertTrue($anne->isAdmin());
        $this->assertContains('ROLE_USER', $anne->getRoles(), 'Bestaande rollen mogen niet verdwijnen.');
    }

    public function testNewAccountRequiresPassword(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute(['email' => 'zonderpw@trepiedi.test']);

        $this->assertSame(Command::INVALID, $exitCode);
        $this->em->clear();
        $this->assertNull($this->em->getRepository(User::class)->findOneBy(['email' => 'zonderpw@trepiedi.test']));
    }
}
