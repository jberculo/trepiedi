<?php

namespace App\Form;

use App\Entity\Pool;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PoolType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'form.pool_name',
            ])
            ->add('code', TextType::class, [
                'label' => 'form.pool_code',
                'help' => 'form.pool_code_help',
            ])
            ->add('default', CheckboxType::class, [
                'label' => 'form.pool_default',
                'required' => false,
                'help' => 'form.pool_default_help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Pool::class]);
    }
}
