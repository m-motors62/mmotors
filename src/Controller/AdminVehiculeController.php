<?php

namespace App\Controller;

use App\Entity\Vehicule;
use App\Entity\VehiculePhoto;
use App\Form\VehiculeToggleStatutType;
use App\Form\VehiculeType;
use App\Repository\ActionLogRepository;
use App\Repository\DossierRepository;
use App\Repository\VehiculeRepository;
use App\Service\ActionLogger;
use App\Service\VehicleApiClient;
use App\Service\VehiculePhotoUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/vehicules')]
#[IsGranted('ROLE_COMMERCIAL')]
class AdminVehiculeController extends AbstractController
{
    #[Route('', name: 'app_admin_vehicule_index')]
    public function index(Request $request, VehiculeRepository $vehiculeRepository): Response
    {
        $includeArchived = $request->query->getBoolean('archives');

        $vehicules = $vehiculeRepository->findAllFiltered($includeArchived);
        $vehiculeIdsWithActiveDossier = $vehiculeRepository->findVehiculeIdsWithActiveDossier();

        return $this->render('admin/vehicule/index.html.twig', [
            'vehicules' => $vehicules,
            'includeArchived' => $includeArchived,
            'vehiculeIdsWithActiveDossier' => $vehiculeIdsWithActiveDossier,
        ]);
    }

    #[Route('/nouveau-location', name: 'app_admin_vehicule_new_location')]
    public function newLocation(Request $request, EntityManagerInterface $entityManager, VehiculePhotoUploader $photoUploader, ActionLogger $actionLogger): Response
    {
        return $this->handleNewVehicule($request, $entityManager, $photoUploader, $actionLogger, 'location');
    }

    #[Route('/nouveau-vente', name: 'app_admin_vehicule_new_vente')]
    public function newVente(Request $request, EntityManagerInterface $entityManager, VehiculePhotoUploader $photoUploader, ActionLogger $actionLogger): Response
    {
        return $this->handleNewVehicule($request, $entityManager, $photoUploader, $actionLogger, 'vente');
    }

    private function handleNewVehicule(Request $request, EntityManagerInterface $entityManager, VehiculePhotoUploader $photoUploader, ActionLogger $actionLogger, string $statut): Response
    {
        $vehicule = new Vehicule();
        $form = $this->createForm(VehiculeType::class, $vehicule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $vehicule->setStatut($statut);

            $photos = $form->get('photos')->getData();
            $position = 0;

            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $maxFileSize = 5 * 1024 * 1024;

            foreach ($photos as $photoFile) {
                if (!in_array($photoFile->getMimeType(), $allowedMimeTypes, true)) {
                    $this->addFlash('warning', sprintf('Le fichier "%s" a ete ignore (format non autorise, seuls JPEG/PNG/WEBP sont acceptes).', $photoFile->getClientOriginalName()));
                    continue;
                }

                if ($photoFile->getSize() > $maxFileSize) {
                    $this->addFlash('warning', sprintf('Le fichier "%s" a ete ignore (taille superieure a 5 Mo).', $photoFile->getClientOriginalName()));
                    continue;
                }

                $filename = $photoUploader->upload($photoFile);

                $vehiculePhoto = new VehiculePhoto();
                $vehiculePhoto->setFilename($filename);
                $vehiculePhoto->setPosition($position);
                $vehicule->addVehiculePhoto($vehiculePhoto);

                $entityManager->persist($vehiculePhoto);

                $position++;
            }

            $entityManager->persist($vehicule);
            $entityManager->flush();

            $actionLogger->log(
                'creation_vehicule',
                sprintf('A ajoute le vehicule %s %s (%s) au catalogue %s', $vehicule->getMarque(), $vehicule->getModele(), $vehicule->getImmatriculation(), $statut),
                'Vehicule',
                $vehicule->getId()
            );

            $this->addFlash('success', sprintf('Vehicule ajoute au catalogue de %s.', $statut));
            return $this->redirectToRoute('app_admin_vehicule_index');
        }

        return $this->render('admin/vehicule/new.html.twig', [
            'form' => $form,
            'mode' => $statut,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_vehicule_edit', requirements: ['id' => '\d+'])]
    public function edit(Vehicule $vehicule, Request $request, EntityManagerInterface $entityManager, VehiculePhotoUploader $photoUploader, ActionLogger $actionLogger, ActionLogRepository $actionLogRepository): Response
    {
        $form = $this->createForm(VehiculeType::class, $vehicule, [
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photos = $form->get('photos')->getData();
            $position = count($vehicule->getVehiculePhotos());

            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $maxFileSize = 5 * 1024 * 1024;

            foreach ($photos as $photoFile) {
                if (!in_array($photoFile->getMimeType(), $allowedMimeTypes, true)) {
                    $this->addFlash('warning', sprintf('Le fichier "%s" a ete ignore (format non autorise).', $photoFile->getClientOriginalName()));
                    continue;
                }

                if ($photoFile->getSize() > $maxFileSize) {
                    $this->addFlash('warning', sprintf('Le fichier "%s" a ete ignore (taille superieure a 5 Mo).', $photoFile->getClientOriginalName()));
                    continue;
                }

                $filename = $photoUploader->upload($photoFile);

                $vehiculePhoto = new VehiculePhoto();
                $vehiculePhoto->setFilename($filename);
                $vehiculePhoto->setPosition($position);
                $vehicule->addVehiculePhoto($vehiculePhoto);

                $entityManager->persist($vehiculePhoto);

                $position++;
            }

            $entityManager->flush();

            $actionLogger->log(
                'modification_vehicule',
                sprintf('A modifie le vehicule %s %s (%s)', $vehicule->getMarque(), $vehicule->getModele(), $vehicule->getImmatriculation()),
                'Vehicule',
                $vehicule->getId()
            );

            $this->addFlash('success', 'Vehicule modifie avec succes.');
            return $this->redirectToRoute('app_admin_vehicule_index');
        }

        return $this->render('admin/vehicule/edit.html.twig', [
            'form' => $form,
            'vehicule' => $vehicule,
            'historique' => $actionLogRepository->findBy(
                ['targetType' => 'Vehicule', 'targetId' => $vehicule->getId()],
                ['createdAt' => 'DESC']
            ),
        ]);
    }

    #[Route('/{id}/basculer', name: 'app_admin_vehicule_toggle_statut', requirements: ['id' => '\d+'])]
    public function toggleStatut(Vehicule $vehicule, Request $request, EntityManagerInterface $entityManager, ActionLogger $actionLogger, DossierRepository $dossierRepository): Response
    {
        $blockingStatuses = ['en_cours', 'valide'];

        $activeDossierCount = $dossierRepository->count([
            'vehicule' => $vehicule,
            'statut' => $blockingStatuses,
        ]);

        if ($activeDossierCount > 0) {
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Ce vehicule a un dossier en cours ou valide et ne peut pas etre bascule.',
                ], 422);
            }
            throw $this->createAccessDeniedException();
        }

        $nouveauStatut = $vehicule->getStatut() === 'location' ? 'vente' : 'location';

        $form = $this->createForm(VehiculeToggleStatutType::class, null, [
            'nouveau_statut' => $nouveauStatut,
        ]);
        $form->handleRequest($request);

        if ($request->isXmlHttpRequest() && !$form->isSubmitted()) {
            return $this->render('admin/vehicule/_toggle_statut_form.html.twig', [
                'form' => $form,
                'vehicule' => $vehicule,
                'nouveauStatut' => $nouveauStatut,
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $ancienStatut = $vehicule->getStatut();
            $ancienPrix = $vehicule->getPrix();
            $nouveauPrix = $form->get('prix')->getData();

            $vehicule->setStatut($nouveauStatut);
            $vehicule->setPrix($nouveauPrix);

            $entityManager->flush();

            $actionLogger->log(
                'bascule_vehicule',
                sprintf('A bascule le vehicule %s %s (%s) de %s (%s EUR) vers %s (%s EUR)', $vehicule->getMarque(), $vehicule->getModele(), $vehicule->getImmatriculation(), $ancienStatut, $ancienPrix, $nouveauStatut, $nouveauPrix),
                'Vehicule',
                $vehicule->getId()
            );

            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true]);
            }

            return $this->redirectToRoute('app_admin_vehicule_index');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => false,
                'html' => $this->renderView('admin/vehicule/_toggle_statut_form.html.twig', [
                    'form' => $form,
                    'vehicule' => $vehicule,
                    'nouveauStatut' => $nouveauStatut,
                ]),
            ], 422);
        }

        return $this->render('admin/vehicule/toggle_statut.html.twig', [
            'form' => $form,
            'vehicule' => $vehicule,
            'nouveauStatut' => $nouveauStatut,
        ]);
    }

    #[Route('/photo/{id}/supprimer', name: 'app_admin_vehicule_photo_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deletePhoto(VehiculePhoto $vehiculePhoto, EntityManagerInterface $entityManager, VehiculePhotoUploader $photoUploader): Response
    {
        $vehicule = $vehiculePhoto->getVehicule();
        $photoUploader->remove($vehiculePhoto->getFilename());
        $vehicule->removeVehiculePhoto($vehiculePhoto);
        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/archiver', name: 'app_admin_vehicule_toggle_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleArchive(Vehicule $vehicule, EntityManagerInterface $entityManager, ActionLogger $actionLogger, DossierRepository $dossierRepository): Response
    {
        if (!$vehicule->isArchived()) {
            $activeDossierCount = $dossierRepository->count([
                'vehicule' => $vehicule,
                'statut' => ['en_cours', 'valide'],
            ]);

            if ($activeDossierCount > 0) {
                return $this->json([
                    'success' => false,
                    'message' => 'Ce vehicule a un dossier en cours ou valide et ne peut pas etre archive.',
                ], 422);
            }
        }

        $vehicule->setIsArchived(!$vehicule->isArchived());
        $entityManager->flush();

        $actionLogger->log(
            $vehicule->isArchived() ? 'archivage_vehicule' : 'desarchivage_vehicule',
            sprintf('%s le vehicule %s %s (%s)', $vehicule->isArchived() ? 'A archive' : 'A desarchive', $vehicule->getMarque(), $vehicule->getModele(), $vehicule->getImmatriculation()),
            'Vehicule',
            $vehicule->getId()
        );

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_vehicule_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Vehicule $vehicule, EntityManagerInterface $entityManager, VehiculePhotoUploader $photoUploader, ActionLogger $actionLogger, DossierRepository $dossierRepository): Response
    {
        $dossierCount = $dossierRepository->count(['vehicule' => $vehicule]);

        if ($dossierCount > 0) {
            return $this->json([
                'success' => false,
                'message' => 'Ce vehicule est rattache a au moins un dossier et ne peut pas etre supprime. Vous pouvez l\'archiver a la place.',
            ], 422);
        }

        $description = sprintf('A supprime le vehicule %s %s (%s)', $vehicule->getMarque(), $vehicule->getModele(), $vehicule->getImmatriculation());
        $vehiculeId = $vehicule->getId();

        foreach ($vehicule->getVehiculePhotos() as $photo) {
            $photoUploader->remove($photo->getFilename());
        }

        $entityManager->remove($vehicule);
        $entityManager->flush();

        $actionLogger->log('suppression_vehicule', $description, 'Vehicule', $vehiculeId);

        return $this->json(['success' => true]);
    }

    #[Route('/api/marques', name: 'app_admin_vehicule_api_marques')]
    public function apiMarques(VehicleApiClient $vehicleApiClient): Response
    {
        return $this->json($vehicleApiClient->getMakes());
    }

    #[Route('/api/modeles/{marque}', name: 'app_admin_vehicule_api_modeles')]
    public function apiModeles(string $marque, VehicleApiClient $vehicleApiClient): Response
    {
        return $this->json($vehicleApiClient->getModelsForMake($marque));
    }
}