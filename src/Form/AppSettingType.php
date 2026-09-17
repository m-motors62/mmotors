<?php

namespace App\Form;

use App\Entity\AppSetting;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class AppSettingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('settingValue', TextType::class, [
                'label' => 'Cle d\'acces a l\'espace admin',
                'help' => 'Uniquement des lettres et des chiffres, 6 caracteres minimum.',
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 6),
                    new Regex(pattern: '/^[a-zA-Z0-9]+$/', message: 'Seuls les lettres et chiffres sont autorises.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AppSetting::class,
        ]);
    }
}