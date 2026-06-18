<?php

namespace App\Tests\Api;

use App\Api\ApiError;
use App\Api\ApiException;
use App\Api\ApiNormalizer;
use App\Api\WriteApi;
use App\Entity\FootballMatch;
use App\Entity\Pool;
use App\Entity\Prediction;
use App\Entity\User;
use App\Pool\PoolCodeGenerator;
use App\Repository\FootballMatchRepository;
use App\Repository\PoolRepository;
use App\Repository\PredictionRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class WriteApiTest extends TestCase
{
    public function testSubmitPredictionOnInactiveMatchThrowsConflict(): void
    {
        $user = (new User())->setEmail('anne@test')->setDisplayName('Anne');
        $match = (new FootballMatch())
            ->setHomeTeam('A')
            ->setAwayTeam('B')
            ->setKickoffAt(new \DateTimeImmutable('+1 day'))
            ->setActive(false);

        $matches = $this->createMock(FootballMatchRepository::class);
        $predictions = $this->createStub(PredictionRepository::class);
        $pools = $this->createStub(PoolRepository::class);
        $codes = $this->createStub(PoolCodeGenerator::class);
        $normalizer = $this->createStub(ApiNormalizer::class);
        $em = $this->createStub(EntityManagerInterface::class);

        $matches->expects($this->once())->method('find')->with(5)->willReturn($match);

        try {
            (new WriteApi($matches, $predictions, $pools, $codes, $normalizer, $em))
                ->submitPrediction($user, 5, ['homeScore' => 1, 'awayScore' => 0, 'advancingSide' => 'home']);
            $this->fail('Expected ApiException was not thrown.');
        } catch (ApiException $e) {
            $this->assertSame(ApiError::Conflict, $e->error);
        }
    }

    public function testCreatePoolWithDefaultUnsetsExistingDefault(): void
    {
        $admin = (new User())->setEmail('admin@test')->setDisplayName('Admin')->setRoles(['ROLE_ADMIN']);
        $existing = (new Pool())->setName('Algemeen')->setCode('algemeen')->setDefault(true);

        $matches = $this->createStub(FootballMatchRepository::class);
        $predictions = $this->createStub(PredictionRepository::class);
        $pools = $this->createMock(PoolRepository::class);
        $codes = $this->createStub(PoolCodeGenerator::class);
        $normalizer = $this->createStub(ApiNormalizer::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $pools->expects($this->once())->method('findOneByCode')->with('vrienden')->willReturn(null);
        $pools->expects($this->once())->method('findBy')->with(['isDefault' => true])->willReturn([$existing]);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(Pool::class));
        $em->expects($this->once())->method('flush');

        $data = (new WriteApi($matches, $predictions, $pools, $codes, $normalizer, $em))
            ->createPool($admin, ['name' => 'Vrienden', 'code' => 'vrienden', 'default' => true]);

        $this->assertFalse($existing->isDefault());
        $this->assertSame('Vrienden', $data['name']);
        $this->assertSame('vrienden', $data['code']);
        $this->assertTrue($data['default']);
    }
}
