<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\User;
use App\Form\UserEditType;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
            $newRole = $form->get('role')->getData();

            /** @var User $currentUser */
            $currentUser = $this->getUser();
            $currentUserIsAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles(), true);
            if ('ROLE_ADMIN' === $newRole && !$currentUserIsAdmin) {
                throw $this->createAccessDeniedException('Seul un administrateur peut attribuer ce role.');
            }

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

    #[Route('/{id}/modifier', name: 'app_admin_user_edit', requirements: ['id' => '\d+'])]
    public function edit(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$user->canBeManagedBy($currentUser)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cet utilisateur.');
        }

        $form = $this->createForm(UserEditType::class, $user);
        $form->handleRequest($request);

        if ($request->isXmlHttpRequest() && !$form->isSubmitted()) {
            return $this->render('admin/user/_edit_form.html.twig', [
                'form' => $form,
                'user' => $user,
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $newRole = $form->get('role')->getData();

            $currentUserIsAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles(), true);
            if ('ROLE_ADMIN' === $newRole && !$currentUserIsAdmin) {
                throw $this->createAccessDeniedException('Seul un administrateur peut attribuer ce role.');
            }

            $user->getContact()->setNom($form->get('nom')->getData());
            $user->getContact()->setPrenom($form->get('prenom')->getData());
            $user->getContact()->setEmail($form->get('email')->getData());
            $user->setEmail($form->get('email')->getData());
            $user->setRoles([$newRole]);

            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => true,
                    'html' => $this->renderView('admin/user/_table.html.twig', [
                        'users' => $entityManager->getRepository(User::class)->findAll(),
                    ]),
                ]);
            }

            $this->addFlash('success', 'Utilisateur modifie avec succes.');
            return $this->redirectToRoute('app_admin_user_index');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => false,
                'html' => $this->renderView('admin/user/_edit_form.html.twig', [
                    'form' => $form,
                    'user' => $user,
                ]),
            ], 422);
        }

        return $this->render('admin/user/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/{id}/renvoyer-lien', name: 'app_admin_user_resend_link', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resendResetLink(User $user, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$user->canBeManagedBy($currentUser)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas gerer cet utilisateur.');
        }

        $token = bin2hex(random_bytes(32));
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+48 hours'));

        $entityManager->flush();

        $resetUrl = $this->generateUrl('app_admin_password_reset', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new Email())
            ->from('m-motors@freemaxi.fr')
            ->to($user->getContact()->getEmail())
            ->subject('M-Motors - Nouveau lien de definition de mot de passe')
            ->text("Bonjour {$user->getContact()->getPrenom()},\n\nUn nouveau lien vous permet de definir votre mot de passe (valable 48h) :\n{$resetUrl}\n\nCordialement,\nL'equipe M-Motors");

        $emailSent = true;

        try {
            $mailer->send($email);
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            $emailSent = false;
        }

        return $this->json(['success' => true, 'emailSent' => $emailSent]);
    }

    #[Route('/{id}/toggle-actif', name: 'app_admin_user_toggle_active', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleActive(User $user, EntityManagerInterface $entityManager): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$user->canBeManagedBy($currentUser)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas gerer cet utilisateur.');
        }

        if ($user->isActive()) {
            $user->setIsActive(false);
            $user->setDeactivatedAt(new \DateTimeImmutable());
            $user->setDeactivatedBy($currentUser);
        } else {
            $user->setIsActive(true);
            $user->setDeactivatedAt(null);
            $user->setDeactivatedBy(null);
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'html' => $this->renderView('admin/user/_table.html.twig', [
                'users' => $entityManager->getRepository(User::class)->findAll(),
            ]),
        ]);
    }
}