<?php

namespace App\Tests\Pool;

use App\Entity\Pool;
use App\Entity\User;
use App\Pool\PoolEnroller;
use App\Pool\PoolJoinManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class PoolJoinManagerTest extends TestCase
{
    public function testRememberAndPendingCodeRoundTrip(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $enroller = $this->createStub(PoolEnroller::class);
        $em = $this->createStub(EntityManagerInterface::class);

        $session->expects($this->once())->method('set')->with(PoolEnroller::SESSION_KEY, 'kantoor');
        $session->expects($this->once())->method('get')->with(PoolEnroller::SESSION_KEY)->willReturn('kantoor');

        $manager = new PoolJoinManager($enroller, $em);
        $manager->rememberCode($session, 'kantoor');

        $this->assertSame('kantoor', $manager->pendingCode($session));
    }

    public function testJoinFlushesOnlyWhenPoolWasJoined(): void
    {
        $user = new User();
        $pool = (new Pool())->setName('Kantoor')->setCode('kantoor');
        $enroller = $this->createMock(PoolEnroller::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $enroller->expects($this->once())->method('enroll')->with($user, 'kantoor')->willReturn($pool);
        $em->expects($this->once())->method('flush');

        $result = (new PoolJoinManager($enroller, $em))->join($user, 'kantoor');

        $this->assertSame($pool, $result);
    }

    public function testJoinDoesNotFlushWhenCodeIsInvalid(): void
    {
        $user = new User();
        $enroller = $this->createMock(PoolEnroller::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $enroller->expects($this->once())->method('enroll')->with($user, 'bestaat-niet')->willReturn(null);
        $em->expects($this->never())->method('flush');

        $this->assertNull((new PoolJoinManager($enroller, $em))->join($user, 'bestaat-niet'));
    }

    public function testConsumePendingJoinForgetsCodeAndReturnsJoined(): void
    {
        $user = new User();
        $pool = (new Pool())->setName('Algemeen')->setCode('algemeen');
        $session = $this->createMock(SessionInterface::class);
        $enroller = $this->createMock(PoolEnroller::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $session->expects($this->once())->method('get')->with(PoolEnroller::SESSION_KEY)->willReturn('algemeen');
        $session->expects($this->once())->method('remove')->with(PoolEnroller::SESSION_KEY);
        $enroller->expects($this->once())->method('enroll')->with($user, 'algemeen')->willReturn($pool);
        $em->expects($this->once())->method('flush');

        $result = (new PoolJoinManager($enroller, $em))->consumePendingJoin($user, $session);

        $this->assertTrue($result->isJoined());
        $this->assertSame($pool, $result->pool);
    }

    public function testConsumePendingJoinReturnsInvalidForBadCode(): void
    {
        $user = new User();
        $session = $this->createMock(SessionInterface::class);
        $enroller = $this->createMock(PoolEnroller::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $session->expects($this->once())->method('get')->with(PoolEnroller::SESSION_KEY)->willReturn('bad');
        $session->expects($this->once())->method('remove')->with(PoolEnroller::SESSION_KEY);
        $enroller->expects($this->once())->method('enroll')->with($user, 'bad')->willReturn(null);
        $em->expects($this->never())->method('flush');

        $result = (new PoolJoinManager($enroller, $em))->consumePendingJoin($user, $session);

        $this->assertTrue($result->isInvalid());
    }
}
