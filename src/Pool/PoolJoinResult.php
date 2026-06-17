<?php

namespace App\Pool;

use App\Entity\Pool;

final class PoolJoinResult
{
    private function __construct(
        public readonly string $status,
        public readonly ?Pool $pool = null,
    ) {
    }

    public static function none(): self
    {
        return new self('none');
    }

    public static function joined(Pool $pool): self
    {
        return new self('joined', $pool);
    }

    public static function invalid(): self
    {
        return new self('invalid');
    }

    public function isJoined(): bool
    {
        return $this->status === 'joined';
    }

    public function isInvalid(): bool
    {
        return $this->status === 'invalid';
    }
}
