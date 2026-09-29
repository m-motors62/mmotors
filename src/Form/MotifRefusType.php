<?php

namespace App\Form;

use App\Entity\MotifRefus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class MotifRefusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Texte du motif',
                'constraints' => [new NotBlank()],
            ])
            ->add('contexte', ChoiceType::class, [
                'label' => 'Contexte',
                'choices' => [
                    'Refus de dossier' => 'dossier',
                    'Rejet de document' => 'document',
                ],
                'constraints' => [new NotBlank()],
            ])
            ->add('typeDocument', ChoiceType::class, [
                'label' => 'Type de document (optionnel)',
                'choices' => [
                    'Tous types' => null,
                    'Carte d\'identite' => 'carte_identite',
                    'Justificatif de domicile' => 'justificatif_domicile',
                    'Fiche de paie' => 'fiche_paie',
                ],
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MotifRefus::class,
        ]);
    }
}