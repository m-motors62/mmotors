<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ResetPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'help' => '8 caracteres minimum, avec au moins une majuscule, une minuscule, un chiffre et un caractere special.',
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                ],
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caracteres'),
                    new Regex(
                        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/',
                        message: 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractere special.',
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}