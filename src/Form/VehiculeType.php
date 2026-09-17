<?php

namespace App\Form;

use App\Entity\Vehicule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Regex;

class VehiculeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('marque', TextType::class, [
                'label' => 'Marque',
                'constraints' => [new NotBlank()],
            ])
            ->add('modele', TextType::class, [
                'label' => 'Modele',
                'constraints' => [new NotBlank()],
            ])
            ->add('motorisation', ChoiceType::class, [
                'label' => 'Motorisation',
                'choices' => [
                    'Essence' => 'Essence',
                    'Diesel' => 'Diesel',
                    'Hybride' => 'Hybride',
                    'Electrique' => 'Electrique',
                    'GPL' => 'GPL',
                ],
                'placeholder' => 'Choisir une motorisation',
                'required' => false,
            ])
            ->add('kilometrage', IntegerType::class, [
                'label' => 'Kilometrage',
                'constraints' => [new NotBlank(), new Positive()],
            ])
            ->add('prix', MoneyType::class, [
                'label' => 'Prix mensuel de location',
                'currency' => 'EUR',
                'constraints' => [new NotBlank(), new Positive()],
            ])
            ->add('etat', TextareaType::class, [
                'label' => 'Etat du vehicule',
                'required' => false,
            ])
            ->add('assurance', CheckboxType::class, [
                'label' => 'Assurance tous risques incluse',
                'required' => false,
            ])
            ->add('assistance', CheckboxType::class, [
                'label' => 'Assistance depannage incluse',
                'required' => false,
            ])
            ->add('entretien', CheckboxType::class, [
                'label' => 'Entretien et SAV inclus',
                'required' => false,
            ])
            ->add('controleTechnique', CheckboxType::class, [
                'label' => 'Controle technique inclus',
                'required' => false,
            ])
            ->add('immatriculation', TextType::class, [
                'label' => 'Immatriculation',
                'constraints' => [
                    new NotBlank(),
                    new Regex(
                        pattern: '/^[A-Z]{2}-\d{3}-[A-Z]{2}$/',
                        message: 'Le format attendu est AA-123-AA.',
                    ),
                ],
            ])
            ->add('photos', FileType::class, [
                'label' => 'Photos du vehicule',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Vehicule::class,
        ]);
    }
}