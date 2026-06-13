<?php

namespace App\Form;

use App\Entity\FootballMatch;
use App\Entity\Round;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FootballMatchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('round', EntityType::class, [
                'class' => Round::class,
                'choice_label' => 'name',
                'label' => 'form.match_round',
            ])
            ->add('homeTeam', TextType::class, [
                'label' => 'form.match_home',
            ])
            ->add('awayTeam', TextType::class, [
                'label' => 'form.match_away',
            ])
            ->add('kickoffAt', DateTimeType::class, [
                'label' => 'form.match_kickoff',
                'widget' => 'single_text',
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'form.match_active',
                'required' => false,
                'help' => 'form.match_active_help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => FootballMatch::class]);
    }
}
