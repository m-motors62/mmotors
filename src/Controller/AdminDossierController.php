<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\Dossier;
use App\Entity\Document;
use App\Service\DocumentUploader;
use App\Service\ActionLogger;
use App\Repository\ActionLogRepository;
use App\Repository\DossierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Route('/admin/dossiers')]
#[IsGranted('ROLE_COMMERCIAL')]
class AdminDossierController extends AbstractController
{
    #[Route('', name: 'app_admin_dossier_index')]
    public function index(DossierRepository $dossierRepository, ActionLogRepository $actionLogRepository): Response
    {
        $dossiers = $dossierRepository->findAllWithRelations();
        $consultedIds = $actionLogRepository->getConsultedDossierIds();

        return $this->render('admin/dossier/index.html.twig', [
            'dossiers' => $dossiers,
            'consultedIds' => $consultedIds,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_dossier_show', requirements: ['id' => '\d+'])]
    public function show(Dossier $dossier, ActionLogger $actionLogger): Response
    {
        /** @var \App\Entity\User $currentUser */
        $currentUser = $this->getUser();

        if (!in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            $actionLogger->log(
                'consultation_dossier',
                sprintf('A consulte le dossier de %s %s', $dossier->getClient()->getContact()->getPrenom(), $dossier->getClient()->getContact()->getNom()),
                'Dossier',
                $dossier->getId()
            );
        }

        return $this->render('admin/dossier/show.html.twig', [
            'dossier' => $dossier,
        ]);
    }

    #[Route('/{id}/valider', name: 'app_admin_dossier_valider', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function valider(Dossier $dossier, EntityManagerInterface $entityManager, ActionLogger $actionLogger, MailerInterface $mailer): Response
    {
        
        if ($this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Les administrateurs ne peuvent pas traiter les dossiers.');
        }

        $dossier->setStatut('valide');
        $entityManager->flush();

        $contact = $dossier->getClient()->getContact();

        $actionLogger->log(
            'validation_dossier',
            sprintf('A valide le dossier de %s %s', $contact->getPrenom(), $contact->getNom()),
            'Dossier',
            $dossier->getId()
        );

        $this->envoyerNotificationClient($mailer, $contact, $dossier, 'valide');

        $this->addFlash('success', 'Dossier valide.');
        return $this->redirectToRoute('app_admin_dossier_show', ['id' => $dossier->getId()]);
    }

    #[Route('/{id}/refuser', name: 'app_admin_dossier_refuser', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function refuser(Dossier $dossier, Request $request, EntityManagerInterface $entityManager, ActionLogger $actionLogger, MailerInterface $mailer): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Les administrateurs ne peuvent pas traiter les dossiers.');
        }

        $motif = $request->request->get('motif');

        if (!$motif) {
            $this->addFlash('danger', 'Un motif est obligatoire pour refuser un dossier.');
            return $this->redirectToRoute('app_admin_dossier_show', ['id' => $dossier->getId()]);
        }

        $dossier->setStatut('refuse');
        $dossier->setMotifRefus($motif);
        $entityManager->flush();

        $contact = $dossier->getClient()->getContact();

        $actionLogger->log(
            'refus_dossier',
            sprintf('A refuse le dossier de %s %s (motif : %s)', $contact->getPrenom(), $contact->getNom(), $motif),
            'Dossier',
            $dossier->getId()
        );

        $this->envoyerNotificationClient($mailer, $contact, $dossier, 'refuse');

        $this->addFlash('success', 'Dossier refuse.');
        return $this->redirectToRoute('app_admin_dossier_show', ['id' => $dossier->getId()]);
    }

    private function envoyerNotificationClient(MailerInterface $mailer, $contact, Dossier $dossier, string $resultat): void
    {
        $vehicule = $dossier->getVehicule();

        $sujet = match ($resultat) {
            'valide' => 'Votre dossier M-Motors a ete valide',
            'refuse' => 'Mise a jour de votre dossier M-Motors',
            default => 'Mise a jour de votre dossier M-Motors',
        };

        $corps = match ($resultat) {
            'valide' => "Bonjour {$contact->getPrenom()},\n\nBonne nouvelle, votre dossier pour le vehicule {$vehicule->getMarque()} {$vehicule->getModele()} a ete valide !\nNotre equipe va revenir vers vous pour la suite des demarches.\n\nCordialement,\nL'equipe M-Motors",
            'refuse' => "Bonjour {$contact->getPrenom()},\n\nNous sommes au regret de vous informer que votre dossier pour le vehicule {$vehicule->getMarque()} {$vehicule->getModele()} n'a pas ete retenu.\nMotif : {$dossier->getMotifRefus()}\n\nVous pouvez consulter le detail depuis votre espace client.\n\nCordialement,\nL'equipe M-Motors",
            default => '',
        };

        $email = (new Email())
            ->from('m-motors@freemaxi.fr')
            ->to($contact->getEmail())
            ->subject($sujet)
            ->text($corps);

        try {
            $mailer->send($email);
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            // Silencieux, coherent avec le reste du projet
        }
    }

    #[Route('/document/{id}/telecharger', name: 'app_admin_document_download', requirements: ['id' => '\d+'])]
    public function downloadDocument(Document $document, DocumentUploader $documentUploader): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Les administrateurs ne peuvent pas consulter les documents.');
        }

        $filePath = $documentUploader->getFilePath($document->getNomFichier());

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException();
        }

        return $this->file($filePath, $document->getNomFichier(), \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_INLINE);
    }
}