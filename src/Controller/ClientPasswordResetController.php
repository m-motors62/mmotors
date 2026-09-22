<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Contact;
use App\Form\ForgotPasswordType;
use App\Form\ResetPasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ClientPasswordResetController extends AbstractController
{
    #[Route('/mot-de-passe-oublie', name: 'app_client_forgot_password')]
    public function forgotPassword(Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ForgotPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $contact = $entityManager->getRepository(Contact::class)->findOneBy(['email' => $email]);
            $client = $contact ? $entityManager->getRepository(Client::class)->findOneBy(['contact' => $contact]) : null;

            if ($client) {
                $token = bin2hex(random_bytes(32));
                $client->setResetToken($token);
                $client->setResetTokenExpiresAt(new \DateTimeImmutable('+2 hours'));
                $entityManager->flush();

                $resetUrl = $this->generateUrl('app_client_reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

                $mailMessage = (new Email())
                    ->from('m-motors@freemaxi.fr')
                    ->to($email)
                    ->subject('Reinitialisation de votre mot de passe M-Motors')
                    ->text("Bonjour {$contact->getPrenom()},\n\nCliquez sur ce lien pour redefinir votre mot de passe (valable 2h) :\n{$resetUrl}\n\nSi vous n'etes pas a l'origine de cette demande, ignorez cet email.\n\nCordialement,\nL'equipe M-Motors");

                try {
                    $mailer->send($mailMessage);
                } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
                }
            }

            $this->addFlash('success', 'Si un compte existe avec cet email, un lien de reinitialisation vient de lui etre envoye.');
            return $this->redirectToRoute('app_client_login');
        }

        return $this->render('client_password_reset/forgot_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'app_client_reset_password')]
    public function resetPassword(string $token, Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $client = $entityManager->getRepository(Client::class)->findOneBy(['resetToken' => $token]);

        if (!$client || $client->getResetTokenExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('danger', 'Ce lien est invalide ou a expire.');
            return $this->redirectToRoute('app_client_forgot_password');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('password')->getData();

            $client->setPassword($passwordHasher->hashPassword($client, $newPassword));
            $client->setResetToken(null);
            $client->setResetTokenExpiresAt(null);

            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a ete redefini. Vous pouvez vous connecter.');
            return $this->redirectToRoute('app_client_login');
        }

        return $this->render('client_password_reset/reset_password.html.twig', [
            'form' => $form,
        ]);
    }
}