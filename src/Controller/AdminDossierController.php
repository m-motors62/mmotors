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

        $aTraiterIds = [];
        foreach ($dossiers as $dossier) {
            $derniereActivite = $dossierRepository->getLastActivityDate($dossier);
            $derniereConsultation = $actionLogRepository->getLastConsultationDate($dossier->getId());

            if (!$derniereConsultation || $derniereActivite > $derniereConsultation) {
                $aTraiterIds[] = $dossier->getId();
            }
        }

        return $this->render('admin/dossier/index.html.twig', [
            'dossiers' => $dossiers,
            'aTraiterIds' => $aTraiterIds,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_dossier_show', requirements: ['id' => '\d+'])]
    public function show(Dossier $dossier, ActionLogger $actionLogger, DossierRepository $dossierRepository, ActionLogRepository $actionLogRepository): Response
    {
        $derniereActivite = $dossierRepository->getLastActivityDate($dossier);
        $derniereConsultation = $actionLogRepository->getLastConsultationDate($dossier->getId());
        $aTraiterIds = (!$derniereConsultation || $derniereActivite > $derniereConsultation) ? [$dossier->getId()] : [];

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
            'aTraiterIds' => $aTraiterIds,
        ]);
    }

    #[Route('/{id}/valider', name: 'app_admin_dossier_valider', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function valider(Dossier $dossier, EntityManagerInterface $entityManager, ActionLogger $actionLogger, MailerInterface $mailer): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        foreach ($dossier->getDocuments() as $document) {
            if ($document->isEstRemplace()) {
                continue;
            }

            if ($document->getStatut() !== 'valide') {
                $this->addFlash('danger', 'Tous les documents doivent etre valides individuellement avant de valider le dossier.');
                return $this->redirectToRoute('app_admin_dossier_show', ['id' => $dossier->getId()]);
            }
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

    #[Route('/document/{id}/valider', name: 'app_admin_document_valider', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function validerDocument(Document $document, EntityManagerInterface $entityManager, ActionLogger $actionLogger): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($document->isEstRemplace()) {
            return $this->json(['success' => false, 'message' => 'Ce document a ete remplace et ne peut plus etre modifie.'], 422);
        }

        $document->setStatut('valide');
        $document->setMotifRejet(null);
        $entityManager->flush();

        $actionLogger->log(
            'validation_document',
            sprintf('A valide un document (%s) du dossier de %s %s', $document->getTypeDocument(), $document->getDossier()->getClient()->getContact()->getPrenom(), $document->getDossier()->getClient()->getContact()->getNom()),
            'Dossier',
            $document->getDossier()->getId()
        );

        return $this->json(['success' => true]);
    }

    #[Route('/document/{id}/rejeter', name: 'app_admin_document_rejeter', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rejeterDocument(Document $document, Request $request, EntityManagerInterface $entityManager, ActionLogger $actionLogger, MailerInterface $mailer): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($document->isEstRemplace()) {
            return $this->json(['success' => false, 'message' => 'Ce document a ete remplace et ne peut plus etre modifie.'], 422);
        }

        $motif = $request->request->get('motif');

        if (!$motif) {
            return $this->json(['success' => false, 'message' => 'Un motif est obligatoire.'], 422);
        }

        $document->setStatut('rejete');
        $document->setMotifRejet($motif);
        $entityManager->flush();

        $dossier = $document->getDossier();
        $contact = $dossier->getClient()->getContact();

        $actionLogger->log(
            'rejet_document',
            sprintf('A rejete un document (%s) du dossier de %s %s (motif : %s)', $document->getTypeDocument(), $contact->getPrenom(), $contact->getNom(), $motif),
            'Dossier',
            $dossier->getId()
        );

        $labelType = [
            'carte_identite' => 'Carte d\'identite',
            'justificatif_domicile' => 'Justificatif de domicile',
            'fiche_paie' => 'Fiche de paie',
        ][$document->getTypeDocument()] ?? $document->getTypeDocument();

        $email = (new Email())
            ->from('m-motors@freemaxi.fr')
            ->to($contact->getEmail())
            ->subject('Un document de votre dossier M-Motors necessite votre attention')
            ->text("Bonjour {$contact->getPrenom()},\n\nLe document \"{$labelType}\" de votre dossier necessite d'etre redepose.\nMotif : {$motif}\n\nConnectez-vous a votre espace client pour le remplacer.\n\nCordialement,\nL'equipe M-Motors");

        try {
            $mailer->send($email);
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
        }

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/terminer', name: 'app_admin_dossier_terminer', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function terminer(Dossier $dossier, EntityManagerInterface $entityManager, ActionLogger $actionLogger): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($dossier->getStatut() !== 'valide' || $dossier->getType() !== 'location') {
            $this->addFlash('danger', 'Seul un dossier de location valide peut etre marque comme termine.');
            return $this->redirectToRoute('app_admin_dossier_show', ['id' => $dossier->getId()]);
        }

        $dossier->setStatut('termine');
        $entityManager->flush();

        $contact = $dossier->getClient()->getContact();

        $actionLogger->log(
            'fin_contrat_dossier',
            sprintf('A marque comme termine le contrat de location de %s %s', $contact->getPrenom(), $contact->getNom()),
            'Dossier',
            $dossier->getId(),
            'Vehicule',
            $dossier->getVehicule()->getId()
        );

        $this->addFlash('success', 'Contrat marque comme termine. Le vehicule peut de nouveau etre bascule si besoin.');
        return $this->redirectToRoute('app_admin_dossier_show', ['id' => $dossier->getId()]);
    }
}