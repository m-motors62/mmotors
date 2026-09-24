<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Document;
use App\Entity\Dossier;
use App\Form\DossierType;
use App\Repository\DossierRepository;
use App\Repository\VehiculeRepository;
use App\Service\ActionLogger;
use App\Service\DocumentUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dossier')]
class DossierController extends AbstractController
{
    private const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];
    private function getMaxFileSize(EntityManagerInterface $entityManager): int
    {
        $setting = $entityManager->getRepository(\App\Entity\AppSetting::class)->findOneBy(['settingKey' => 'max_file_size_mo']);
        $mo = $setting ? (int) $setting->getSettingValue() : 5;

        return $mo * 1024 * 1024;
    }

    #[Route('/nouveau/{vehiculeId}', name: 'app_dossier_new', requirements: ['vehiculeId' => '\d+'])]
    #[IsGranted('ROLE_CLIENT')]
    public function new(int $vehiculeId, Request $request, VehiculeRepository $vehiculeRepository, DossierRepository $dossierRepository, EntityManagerInterface $entityManager, DocumentUploader $documentUploader, MailerInterface $mailer, ActionLogger $actionLogger): Response
    {
        $vehicule = $vehiculeRepository->find($vehiculeId);

        if (!$vehicule || $vehicule->isArchived()) {
            throw $this->createNotFoundException();
        }

        /** @var Client $client */
        $client = $this->getUser();

        if ($dossierRepository->hasActiveDossierFor($client, $vehicule)) {
            $this->addFlash('warning', 'Vous avez deja un dossier en cours pour ce vehicule.');
            return $this->redirectToRoute('app_espace_client_dashboard');
        }

        $typeDossier = $vehicule->getStatut() === 'location' ? 'location' : 'achat';

        $form = $this->createForm(DossierType::class, null, [
            'type_dossier' => $typeDossier,
        ]);
        $form->handleRequest($request);

        $maxFileSize = $this->getMaxFileSize($entityManager);
        $maxFileSizeMo = $maxFileSize / (1024 * 1024);

        if ($form->isSubmitted() && $form->isValid()) {
            $dossier = new Dossier();
            $dossier->setType($typeDossier);
            $dossier->setStatut('en_cours');
            $dossier->setDateCreation(new \DateTimeImmutable());
            $dossier->setClient($client);
            $dossier->setVehicule($vehicule);

            $entityManager->persist($dossier);

            $documentsAVerifier = [
                'carteIdentite' => 'carte_identite',
                'justificatifDomicile' => 'justificatif_domicile',
            ];

            if ($typeDossier === 'location') {
                $documentsAVerifier['fichePaie1'] = 'fiche_paie';
                $documentsAVerifier['fichePaie2'] = 'fiche_paie';
                $documentsAVerifier['fichePaie3'] = 'fiche_paie';
            }

            $erreurFichier = false;

            foreach ($documentsAVerifier as $champ => $typeDocument) {
                $file = $form->get($champ)->getData();

                if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
                    $this->addFlash('danger', sprintf('Le fichier "%s" doit etre au format PDF ou JPEG.', $file->getClientOriginalName()));
                    $erreurFichier = true;
                    continue;
                }

                if ($file->getSize() > $maxFileSize) {
                    $this->addFlash('danger', sprintf('Le fichier "%s" depasse la taille maximale de 5 Mo.', $file->getClientOriginalName()));
                    $erreurFichier = true;
                    continue;
                }

                $filename = $documentUploader->upload($file);

                $document = new Document();
                $document->setTypeDocument($typeDocument);
                $document->setNomFichier($filename);
                $document->setDateDepot(new \DateTimeImmutable());
                $document->setStatut('en_attente');
                $document->setDossier($dossier);

                $entityManager->persist($document);
            }

            if ($erreurFichier) {
                return $this->render('dossier/new.html.twig', [
                    'form' => $form,
                    'vehicule' => $vehicule,
                    'typeDossier' => $typeDossier,
                    'maxFileSizeMo' => $maxFileSizeMo,
                ]);
            }

            $entityManager->flush();

            $actionLogger->log(
                'creation_dossier',
                sprintf('A depose un dossier de %s pour le vehicule %s %s (%s)', $typeDossier, $vehicule->getMarque(), $vehicule->getModele(), $vehicule->getImmatriculation()),
                'Dossier',
                $dossier->getId()
            );

            $contact = $client->getContact();

            $actionLogger->log(
                'depot_dossier_vehicule',
                sprintf('Un dossier de %s a ete depose par %s %s', $typeDossier, $contact->getPrenom(), $contact->getNom()),
                'Vehicule',
                $vehicule->getId()
            );

            $contact = $client->getContact();
            $mailMessage = (new Email())
                ->from('m-motors@freemaxi.fr')
                ->to($contact->getEmail())
                ->subject('Confirmation de depot de votre dossier M-Motors')
                ->text("Bonjour {$contact->getPrenom()},\n\nNous avons bien recu votre dossier pour le vehicule {$vehicule->getMarque()} {$vehicule->getModele()}.\nNotre equipe va l'etudier dans les meilleurs delais.\n\nCordialement,\nL'equipe M-Motors");

            try {
                $mailer->send($mailMessage);
            } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
                $this->addFlash('warning', 'Dossier depose, mais l\'envoi de l\'email de confirmation a echoue.');
            }

            $this->addFlash('success', 'Votre dossier a ete depose avec succes.');
            return $this->redirectToRoute('app_espace_client_dashboard');
        }

        return $this->render('dossier/new.html.twig', [
            'form' => $form,
            'vehicule' => $vehicule,
            'typeDossier' => $typeDossier,
            'maxFileSizeMo' => $maxFileSizeMo,
        ]);
    }

    #[Route('/document/{id}/telecharger', name: 'app_document_download', requirements: ['id' => '\d+'])]
    public function downloadDocument(Document $document, DocumentUploader $documentUploader): Response
    {
        $dossier = $document->getDossier();

        /** @var Client|null $client */
        $client = $this->getUser() instanceof Client ? $this->getUser() : null;

        $estProprietaire = $client && $dossier->getClient()->getId() === $client->getId();
        $estCommercial = $this->isGranted('ROLE_COMMERCIAL') && !$this->isGranted('ROLE_ADMIN');

        if (!$estProprietaire && !$estCommercial) {
            throw $this->createAccessDeniedException();
        }

        $filePath = $documentUploader->getFilePath($document->getNomFichier());

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException();
        }

        return $this->file($filePath, $document->getNomFichier(), \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_INLINE);
    }
}