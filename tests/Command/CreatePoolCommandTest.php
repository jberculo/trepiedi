<?php

namespace App\Tests\Command;

use App\Entity\Pool;
use App\Tests\FixturesWebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CreatePoolCommandTest extends FixturesWebTestCase
{
    private function tester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('app:create-pool'));
    }

    public function testCreatesPoolWithExplicitCode(): void
    {
        $tester = $this->tester();
        $tester->execute([
            'name' => 'Vrienden',
            '--code' => 'vrienden',
        ]);
        $tester->assertCommandIsSuccessful();

        $this->em->clear();
        $pool = $this->em->getRepository(Pool::class)->findOneBy(['code' => 'vrienden']);
        $this->assertNotNull($pool);
        $this->assertSame('Vrienden', $pool->getName());
    }

    public function testDefaultFlagMovesToNewPool(): void
    {
        $tester = $this->tester();
        $tester->execute([
            'name' => 'Kantoor 2',
            '--code' => 'kantoor-2',
            '--default' => true,
        ]);
        $tester->assertCommandIsSuccessful();

        $this->em->clear();
        $new = $this->em->getRepository(Pool::class)->findOneBy(['code' => 'kantoor-2']);
        $old = $this->em->getRepository(Pool::class)->findOneBy(['code' => 'algemeen']);

        $this->assertTrue($new->isDefault());
        $this->assertFalse($old->isDefault());
    }

    public function testDuplicateCodeFails(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute([
            'name' => 'Dubbel',
            '--code' => 'algemeen',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
    }
}
