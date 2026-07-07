<?php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Image;

/**
 * Gedeelde avatar-velden (upload + crop-uitsnede) voor zowel het account- als het
 * beheerformulier, zodat de upload-constraints op één plek staan. De maxWidth/maxHeight
 * begrenzen de afmetingen (via getimagesize, vóór de GD-decode) tegen een decompressie-bom:
 * een klein bestand dat naar honderden megapixels uitpakt.
 */
final class AvatarField
{
    public static function addTo(FormBuilderInterface $builder): void
    {
        $builder
            ->add('avatar', FileType::class, [
                'label' => 'account.photo',
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => 'image/*', 'data-avatar-crop' => true],
                'constraints' => [
                    new Image(maxSize: '2M', maxWidth: 6000, maxHeight: 6000),
                ],
            ])
            // Door de crop-UI gevuld met "x,y,size" (bronpixels); leeg = midden-crop.
            ->add('crop', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['data-avatar-crop-data' => true],
            ]);
    }
}
