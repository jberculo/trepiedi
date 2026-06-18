<?php

namespace App\Tests\Pool;

use App\Pool\PoolCodeGenerator;
use App\Repository\PoolRepository;
use PHPUnit\Framework\TestCase;

class PoolCodeGeneratorTest extends TestCase
{
    public function testGenerateReturnsReadableUniqueSizedCode(): void
    {
        $repo = $this->createMock(PoolRepository::class);
        $repo->expects($this->once())->method('findOneByCode')->willReturn(null);

        $code = (new PoolCodeGenerator($repo))->generate('Het Kantoor Voor Internationale Wedstrijden');

        $this->assertMatchesRegularExpression('/^[a-z0-9-]+-[a-f0-9]{4}$/', $code);
        $this->assertLessThanOrEqual(32, strlen($code));
    }
}
