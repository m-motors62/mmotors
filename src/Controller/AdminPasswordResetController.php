<?php

namespace App\Controller;

use App\Form\ForgotPasswordType;
use App\Form\ResetPasswordType;
use App\Repository\UserRepository;
use App\Service\ActionLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AdminPasswordResetController extends AbstractController
{
    #[Route('/admin/reinitialiser/{token}', name: 'app_admin_password_reset')]
    public function reset(string $token, Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $userRepository->findOneBy(['resetToken' => $token]);

        if (!$user || $user->getResetTokenExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('danger', 'Ce lien est invalide ou a expire.');
            return $this->redirectToRoute('app_admin_login');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('password')->getData();

            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);

            $entityManager->flush();

            // Le token etait deja une preuve de legitimite suffisante, on valide la session
            // pour eviter un nouveau blocage par la cle d'acces juste apres
            $request->getSession()->set('admin_access_key_verified', true);

            $this->addFlash('success', 'Votre mot de passe a ete redefini. Vous pouvez vous connecter.');
            return $this->redirectToRoute('app_admin_login');
        }

        return $this->render('admin/password_reset/reset.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/admin/mot-de-passe-oublie', name: 'app_admin_forgot_password')]
    public function forgotPassword(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager, MailerInterface $mailer, ActionLogger $actionLogger): Response
    {
        $form = $this->createForm(ForgotPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $userRepository->findOneBy(['email' => $form->get('email')->getData()]);

            if ($user && $user->isActive()) {
                $token = bin2hex(random_bytes(32));
                $user->setResetToken($token);
                $user->setResetTokenExpiresAt(new \DateTimeImmutable('+2 hours'));
                $entityManager->flush();

                $contact = $user->getContact();
                $resetUrl = $this->generateUrl('app_admin_password_reset', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

                try {
                    $mailer->send((new Email())
                        ->from('m-motors@freemaxi.fr')
                        ->to($user->getEmail())
                        ->subject('Reinitialisation de votre mot de passe M-Motors (back-office)')
                        ->text("Bonjour {$contact->getPrenom()},\n\nCliquez sur ce lien pour redefinir votre mot de passe (valable 2h) :\n{$resetUrl}\n\nSi vous n'etes pas a l'origine de cette demande, ignorez cet email.\n\nCordialement,\nL'equipe M-Motors"));
                } catch (TransportExceptionInterface) {
                }

                $actionLogger->log(
                    'demande_reinitialisation_mdp',
                    sprintf('Demande de reinitialisation du mot de passe pour %s %s', $contact->getPrenom(), $contact->getNom()),
                    'User',
                    $user->getId()
                );
            }

            $this->addFlash('success', 'Si un compte actif existe avec cette adresse, un lien de reinitialisation vient de lui etre envoye.');

            return $this->redirectToRoute('app_admin_login');
        }

        return $this->render('admin/password_reset/forgot.html.twig', ['form' => $form]);
    }
}