<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class AccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('displayName', TextType::class, [
                'label' => 'auth.display_name',
            ])
            ->add('avatar', FileType::class, [
                'label' => 'account.photo',
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => 'image/*', 'data-avatar-crop' => true],
                'constraints' => [
                    new Image(maxSize: '2M'),
                ],
            ])
            // Door de crop-UI gevuld met "x,y,size" (bronpixels); leeg = midden-crop.
            ->add('crop', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['data-avatar-crop-data' => true],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
