<?php

namespace App\Form;

use App\Entity\FootballMatch;
use App\Entity\Prediction;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PredictionFormFactory
{
    public function __construct(
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function create(FootballMatch $match, ?Prediction $prediction = null): FormInterface
    {
        return $this->forms->create(PredictionType::class, $prediction ?? new Prediction(), [
            'match' => $match,
            'action' => $this->urls->generate('app_prediction_save', ['id' => $match->getId()]),
        ]);
    }

    public function createView(FootballMatch $match, ?Prediction $prediction = null): FormView
    {
        return $this->create($match, $prediction)->createView();
    }
}
