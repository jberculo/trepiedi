<?php

namespace App\Form;

use App\Entity\FootballMatch;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Uitslag invoeren: stand na reguliere speeltijd én eventuele verlenging
 * (zonder penalty's) + welke kant doorgaat.
 */
class MatchResultType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FootballMatch $match */
        $match = $options['match'];

        $builder
            ->add('homeScore', IntegerType::class, [
                'label' => 'form.goals_for',
                'label_translation_parameters' => ['%team%' => (string) $match->getHomeTeam()],
                'attr' => ['min' => 0, 'max' => 99],
            ])
            ->add('awayScore', IntegerType::class, [
                'label' => 'form.goals_for',
                'label_translation_parameters' => ['%team%' => (string) $match->getAwayTeam()],
                'attr' => ['min' => 0, 'max' => 99],
            ])
            ->add('advancingSide', ChoiceType::class, [
                'label' => 'form.winner',
                'placeholder' => 'form.choose_team',
                'choices' => [
                    (string) $match->getHomeTeam() => FootballMatch::SIDE_HOME,
                    (string) $match->getAwayTeam() => FootballMatch::SIDE_AWAY,
                ],
            ])
            ->add('finished', CheckboxType::class, [
                'label' => 'form.result_final',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => FootballMatch::class]);
        $resolver->setRequired('match');
        $resolver->setAllowedTypes('match', FootballMatch::class);
    }
}
