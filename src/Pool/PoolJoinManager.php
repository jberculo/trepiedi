<?php

namespace App\Pool;

use App\Entity\Pool;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class PoolJoinManager
{
    public function __construct(
        private PoolEnroller $enroller,
        private EntityManagerInterface $em,
    ) {
    }

    public function rememberCode(SessionInterface $session, string $code): void
    {
        $session->set(PoolEnroller::SESSION_KEY, $code);
    }

    public function pendingCode(SessionInterface $session): ?string
    {
        $code = $session->get(PoolEnroller::SESSION_KEY);

        return is_string($code) && $code !== '' ? $code : null;
    }

    public function forgetPendingCode(SessionInterface $session): void
    {
        $session->remove(PoolEnroller::SESSION_KEY);
    }

    public function isValidCode(?string $code): bool
    {
        return $this->enroller->isValidCode($code);
    }

    public function join(User $user, ?string $code): ?Pool
    {
        return $this->enrollAndFlush($user, $code);
    }

    public function consumePendingJoin(User $user, SessionInterface $session): PoolJoinResult
    {
        $code = $this->pendingCode($session);
        if ($code === null) {
            return PoolJoinResult::none();
        }

        $this->forgetPendingCode($session);
        $pool = $this->enrollAndFlush($user, $code);

        if ($pool === null) {
            return PoolJoinResult::invalid();
        }

        return PoolJoinResult::joined($pool);
    }

    private function enrollAndFlush(User $user, ?string $code): ?Pool
    {
        $pool = $this->enroller->enroll($user, $code);
        if ($pool !== null) {
            $this->em->flush();
        }

        return $pool;
    }
}
