<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserEditType extends AbstractType
{
    public function __construct(private Security $security)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $isAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles(), true);

        $roleChoices = [
            'Commercial' => 'ROLE_COMMERCIAL',
            'Gestionnaire (RH)' => 'ROLE_GESTIONNAIRE',
        ];

        if ($isAdmin) {
            $roleChoices['Administrateur'] = 'ROLE_ADMIN';
        }

        $builder
            ->add('nom', TextType::class, [
                'mapped' => false,
                'data' => $options['data']->getContact()->getNom(),
                'constraints' => [new NotBlank()],
            ])
            ->add('prenom', TextType::class, [
                'mapped' => false,
                'data' => $options['data']->getContact()->getPrenom(),
                'constraints' => [new NotBlank()],
            ])
            ->add('email', EmailType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('role', ChoiceType::class, [
                'mapped' => false,
                'data' => $options['data']->getRoles()[0] ?? 'ROLE_COMMERCIAL',
                'choices' => $roleChoices,
                'constraints' => [new NotBlank()],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}