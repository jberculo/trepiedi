<?php

namespace App\Tests\Entity;

use App\Entity\Pool;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class PoolTest extends TestCase
{
    public function testPoolBasics(): void
    {
        $pool = (new Pool())->setName('Kantoor')->setCode('kantoor')->setDefault(true);

        $this->assertSame('Kantoor', $pool->getName());
        $this->assertSame('kantoor', $pool->getCode());
        $this->assertTrue($pool->isDefault());
        $this->assertSame('Kantoor', (string) $pool);
        $this->assertCount(0, $pool->getMembers());
    }

    public function testUserMembershipAndActivePool(): void
    {
        $a = (new Pool())->setName('Algemeen')->setCode('algemeen');
        $b = (new Pool())->setName('Kantoor')->setCode('kantoor');
        $user = new User();

        $this->assertFalse($user->isInPool($a));

        $user->addPool($a)->addPool($b)->addPool($a); // dubbel toevoegen telt niet
        $this->assertCount(2, $user->getPools());
        $this->assertTrue($user->isInPool($a));

        $user->setActivePool($b);
        $this->assertSame($b, $user->getActivePool());

        $user->removePool($b);
        $this->assertFalse($user->isInPool($b));
        $this->assertCount(1, $user->getPools());
    }
}
