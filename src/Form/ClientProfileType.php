<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ClientProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'mapped' => false,
                'constraints' => [new NotBlank()],
            ])
            ->add('prenom', TextType::class, [
                'mapped' => false,
                'constraints' => [new NotBlank()],
            ])
            ->add('telephone', TelType::class, [
                'mapped' => false,
                'constraints' => [new NotBlank()],
            ])
            ->add('adresse', TextType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['autocomplete' => 'off'],
            ])
            ->add('codePostal', TextType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('ville', TextType::class, [
                'mapped' => false,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}