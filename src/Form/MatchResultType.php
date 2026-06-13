<?php

namespace App\Form;

use App\Entity\FootballMatch;
use App\Entity\Team;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Uitslag invoeren: stand na reguliere speeltijd én eventuele verlenging
 * (zonder penalty's) + de winnaar.
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
            ->add('advancingTeam', EntityType::class, [
                'class' => Team::class,
                'choices' => [$match->getHomeTeam(), $match->getAwayTeam()],
                'choice_label' => 'name',
                'label' => 'form.winner',
                'placeholder' => 'form.choose_team',
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
