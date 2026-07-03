<?php

namespace App\Form;

use App\Entity\Pool;
use App\Entity\User;
use App\Notice\NoticeType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

/**
 * Beheerformulier voor een deelnemer: naam, e-mail, beheerrechten en poules.
 * De beheer-checkbox is niet gemapt; de controller vertaalt 'm naar de rollen.
 */
class UserAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('displayName', TextType::class, [
                'label' => 'auth.display_name',
            ])
            ->add('email', EmailType::class, [
                'label' => 'auth.email',
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
            ->add('crop', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['data-avatar-crop-data' => true],
            ])
            ->add('isAdmin', CheckboxType::class, [
                'label' => 'admin.is_admin',
                'mapped' => false,
                'required' => false,
            ])
            ->add('pools', EntityType::class, [
                'class' => Pool::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
                'label' => 'admin.pools',
            ])
            ->add('notice', TextareaType::class, [
                'label' => 'admin.notice',
                'help' => 'admin.notice_help',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('noticeType', EnumType::class, [
                'class' => NoticeType::class,
                'label' => 'admin.notice_type',
                'choice_label' => static fn (NoticeType $type): string => $type->label(),
            ]);

        // Een beheerder mag het wachtwoord van een gewone gebruiker resetten, maar
        // niet dat van een andere beheerder (en dus ook niet van zichzelf).
        if ($options['allow_password_reset']) {
            $builder->add('newPassword', NewPasswordType::class, [
                'required' => false,
                'first_options' => [
                    'label' => 'account.new_password',
                    'attr' => ['autocomplete' => 'new-password'],
                    'help' => 'admin.password_reset_help',
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'allow_password_reset' => false,
        ]);
        $resolver->setAllowedTypes('allow_password_reset', 'bool');
    }
}
