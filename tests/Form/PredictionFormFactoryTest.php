<?php

namespace App\Tests\Form;

use App\Entity\FootballMatch;
use App\Form\PredictionFormFactory;
use App\Form\PredictionType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PredictionFormFactoryTest extends TestCase
{
    public function testCreatePassesMatchAndActionOptions(): void
    {
        $match = (new FootballMatch())
            ->setHomeTeam('Nederland')
            ->setAwayTeam('Duitsland')
            ->setKickoffAt(new \DateTimeImmutable('+1 day'));
        $this->setId($match, 42);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $forms = $this->createMock(FormFactoryInterface::class);
        $form = $this->createStub(FormInterface::class);

        $urls->expects($this->once())
            ->method('generate')
            ->with('app_prediction_save', ['id' => 42])
            ->willReturn('/voorspelling/42/opslaan');

        $forms->expects($this->once())
            ->method('create')
            ->with(
                PredictionType::class,
                $this->isInstanceOf(\App\Entity\Prediction::class),
                [
                    'match' => $match,
                    'action' => '/voorspelling/42/opslaan',
                ],
            )
            ->willReturn($form);

        $this->assertSame($form, (new PredictionFormFactory($forms, $urls))->create($match));
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setValue($entity, $id);
    }
}
