<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Contact;
use App\Form\ClientRegistrationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ClientRegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_client_registration')]
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, MailerInterface $mailer, ValidatorInterface $validator): Response
    {
        $form = $this->createForm(ClientRegistrationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();

            $emailErrors = $validator->validate($email, [
                new Assert\NotBlank(),
                new Assert\Email(),
            ]);

            $existingContact = $entityManager->getRepository(Contact::class)->findOneBy(['email' => $email]);

            if ($existingContact) {
                $this->addFlash('danger', 'Un compte existe deja avec cet email.');
                return $this->render('client_registration/register.html.twig', [
                    'form' => $form,
                ]);
            }

            $contact = new Contact();
            $contact->setNom($form->get('nom')->getData());
            $contact->setPrenom($form->get('prenom')->getData());
            $contact->setEmail($email);
            $contact->setTelephone($form->get('telephone')->getData());

            $token = bin2hex(random_bytes(32));

            $client = new Client();
            $client->setContact($contact);
            $client->setPassword($passwordHasher->hashPassword($client, $form->get('password')->getData()));
            $client->setIsVerified(false);
            $client->setVerificationToken($token);

            $entityManager->persist($contact);
            $entityManager->persist($client);
            $entityManager->flush();

            $confirmUrl = $this->generateUrl('app_client_verify_email', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

            $mailMessage = (new Email())
                ->from('m-motors@freemaxi.fr')
                ->to($email)
                ->subject('Confirmez votre inscription chez M-Motors')
                ->text("Bonjour {$contact->getPrenom()},\n\nMerci de vous etre inscrit sur M-Motors.\nCliquez sur ce lien pour confirmer votre email et activer votre compte :\n{$confirmUrl}\n\nCordialement,\nL'equipe M-Motors");

            try {
                $mailer->send($mailMessage);
                $this->addFlash('success', 'Inscription reussie. Verifiez votre boite mail pour activer votre compte.');
            } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
                $this->addFlash('warning', 'Compte cree, mais l\'envoi de l\'email de confirmation a echoue.');
            }

            return $this->redirectToRoute('app_client_login');
        }

        return $this->render('client_registration/register.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/verifier-email/{token}', name: 'app_client_verify_email')]
    public function verifyEmail(string $token, EntityManagerInterface $entityManager): Response
    {
        $client = $entityManager->getRepository(Client::class)->findOneBy(['verificationToken' => $token]);

        if (!$client) {
            $this->addFlash('danger', 'Ce lien de confirmation est invalide.');
            return $this->redirectToRoute('app_client_login');
        }

        $client->setIsVerified(true);
        $client->setVerificationToken(null);
        $entityManager->flush();

        $this->addFlash('success', 'Votre email a ete confirme. Vous pouvez maintenant vous connecter.');
        return $this->redirectToRoute('app_client_login');
    }
}