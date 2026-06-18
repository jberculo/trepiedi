<?php

namespace App\Tests\Locale;

use App\Entity\User;
use App\Locale\LocaleManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LocaleManagerTest extends TestCase
{
    public function testApplyUserLocaleStoresLocaleInSession(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $session = $this->createMock(SessionInterface::class);
        $user = (new User())->setLocale('en');

        $session->expects($this->once())->method('set')->with('_locale', 'en');

        (new LocaleManager($em))->applyUserLocale($session, $user);
    }

    public function testSwitchLocalePersistsLoggedInUser(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $session = $this->createMock(SessionInterface::class);
        $user = (new User())->setLocale('nl');

        $session->expects($this->once())->method('set')->with('_locale', 'en');
        $em->expects($this->once())->method('flush');

        (new LocaleManager($em))->switchLocale($session, $user, 'en');

        $this->assertSame('en', $user->getLocale());
    }

    public function testSwitchLocaleForAnonymousOnlyTouchesSession(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $session = $this->createMock(SessionInterface::class);

        $session->expects($this->once())->method('set')->with('_locale', 'nl');
        $em->expects($this->never())->method('flush');

        (new LocaleManager($em))->switchLocale($session, null, 'nl');
    }
}
