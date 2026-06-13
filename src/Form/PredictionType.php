<?php

namespace App\Form;

use App\Entity\FootballMatch;
use App\Entity\Prediction;
use App\Entity\Team;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

class PredictionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FootballMatch $match */
        $match = $options['match'];

        $builder
            ->add('homeScore', IntegerType::class, [
                'label' => $match->getHomeTeam(),
                'attr' => ['min' => 0, 'max' => 99],
            ])
            ->add('awayScore', IntegerType::class, [
                'label' => $match->getAwayTeam(),
                'attr' => ['min' => 0, 'max' => 99],
            ])
            ->add('advancingTeam', EntityType::class, [
                'class' => Team::class,
                'choices' => [$match->getHomeTeam(), $match->getAwayTeam()],
                'choice_label' => 'name',
                'label' => 'form.winner',
                'placeholder' => 'form.choose_team',
                'constraints' => [new NotNull(message: 'validation.choose_winner')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Prediction::class,
        ]);
        $resolver->setRequired('match');
        $resolver->setAllowedTypes('match', FootballMatch::class);
    }
}
