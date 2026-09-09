<?php

namespace App\Command;

use App\Entity\Contact;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Cree un compte administrateur pour le back-office',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $nom = $io->ask('Nom');
        $prenom = $io->ask('Prenom');
        $email = $io->ask('Email');
        $password = $io->askHidden('Mot de passe');

        $contact = new Contact();
        $contact->setNom($nom);
        $contact->setPrenom($prenom);
        $contact->setEmail($email);

        $user = new User();
        $user->setContact($contact);
        $user->setEmail($email);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setIsActive(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($contact);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Compte admin cree pour %s %s (%s)', $prenom, $nom, $email));

        return Command::SUCCESS;
    }
}