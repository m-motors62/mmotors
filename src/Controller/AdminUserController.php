<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/admin/utilisateurs')]
#[IsGranted('ROLE_GESTIONNAIRE')]
class AdminUserController extends AbstractController
{
    #[Route('', name: 'app_admin_user_index')]
    public function index(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/nouveau', name: 'app_admin_user_new')]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, MailerInterface $mailer): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($request->isXmlHttpRequest() && !$form->isSubmitted()) {
            return $this->render('admin/user/_form.html.twig', [
                'form' => $form,
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $contact = new Contact();
            $contact->setNom($form->get('nom')->getData());
            $contact->setPrenom($form->get('prenom')->getData());
            $contact->setEmail($form->get('email')->getData());

            $token = bin2hex(random_bytes(32));

            $user->setContact($contact);
            $user->setRoles([$form->get('role')->getData()]);
            $user->setIsActive(true);
            $user->setPassword($passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
            $user->setResetToken($token);
            $user->setResetTokenExpiresAt(new \DateTimeImmutable('+48 hours'));

            $entityManager->persist($contact);
            $entityManager->persist($user);
            $entityManager->flush();

            $resetUrl = $this->generateUrl('app_admin_password_reset', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

            $email = (new Email())
                ->from('m-motors@freemaxi.fr')
                ->to($contact->getEmail())
                ->subject('Bienvenue chez M-Motors - Definissez votre mot de passe')
                ->text("Bonjour {$contact->getPrenom()},\n\nUn compte a ete cree pour vous sur le back-office M-Motors.\nCliquez sur ce lien pour definir votre mot de passe (valable 48h) :\n{$resetUrl}\n\nCordialement,\nL'equipe M-Motors");

            $emailSent = true;
            $emailError = null;

            try {
                $mailer->send($email);
            } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
                $emailSent = false;
                $emailError = $e->getMessage();
            }

            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => true,
                    'emailSent' => $emailSent,
                    'html' => $this->renderView('admin/user/_table.html.twig', [
                        'users' => $entityManager->getRepository(User::class)->findAll(),
                    ]),
                ]);
            }

            if ($emailSent) {
                $this->addFlash('success', 'Utilisateur cree, un email lui a ete envoye.');
            } else {
                $this->addFlash('warning', sprintf('Utilisateur cree, mais l\'envoi de l\'email a echoue : %s', $emailError));
            }

            return $this->redirectToRoute('app_admin_user_index');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => false,
                'html' => $this->renderView('admin/user/_form.html.twig', [
                    'form' => $form,
                ]),
            ], 422);
        }

        return $this->render('admin/user/new.html.twig', [
            'form' => $form,
        ]);
    }
}