<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class DossierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $typeDossier = $options['type_dossier'];

        $builder
            ->add('carteIdentite', FileType::class, [
                'label' => 'Carte d\'identite',
                'mapped' => false,
                'constraints' => [
                    new NotBlank(message: 'Ce document est obligatoire.'),
                ],
            ])
            ->add('justificatifDomicile', FileType::class, [
                'label' => 'Justificatif de domicile',
                'mapped' => false,
                'constraints' => [
                    new NotBlank(message: 'Ce document est obligatoire.'),
                ],
            ])
        ;

        if ($typeDossier === 'location') {
            $builder
                ->add('fichePaie1', FileType::class, [
                    'label' => 'Fiche de paie (mois 1)',
                    'mapped' => false,
                    'constraints' => [
                        new NotBlank(message: 'Ce document est obligatoire.'),
                    ],
                ])
                ->add('fichePaie2', FileType::class, [
                    'label' => 'Fiche de paie (mois 2)',
                    'mapped' => false,
                    'constraints' => [
                        new NotBlank(message: 'Ce document est obligatoire.'),
                    ],
                ])
                ->add('fichePaie3', FileType::class, [
                    'label' => 'Fiche de paie (mois 3)',
                    'mapped' => false,
                    'constraints' => [
                        new NotBlank(message: 'Ce document est obligatoire.'),
                    ],
                ])
            ;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'type_dossier' => null,
        ]);
        $resolver->setRequired('type_dossier');
    }
}