<?php

namespace App\Controller;

use App\Entity\Vehicule;
use App\Entity\VehiculePhoto;
use App\Form\VehiculeType;
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

        return $this->render('admin/vehicule/index.html.twig', [
            'vehicules' => $vehicules,
            'includeArchived' => $includeArchived,
        ]);
    }

    #[Route('/nouveau-location', name: 'app_admin_vehicule_new_location')]
    public function newLocation(Request $request, EntityManagerInterface $entityManager, VehiculePhotoUploader $photoUploader, ActionLogger $actionLogger): Response
    {
        $vehicule = new Vehicule();
        $form = $this->createForm(VehiculeType::class, $vehicule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $vehicule->setStatut('location');

            $photos = $form->get('photos')->getData();
            $position = 0;

            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $maxFileSize = 5 * 1024 * 1024; // 5 Mo

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
                sprintf('A ajoute le vehicule %s %s (%s) au catalogue location', $vehicule->getMarque(), $vehicule->getModele(), $vehicule->getImmatriculation()),
                'Vehicule',
                $vehicule->getId()
            );

            $this->addFlash('success', 'Vehicule ajoute au catalogue de location.');
            return $this->redirectToRoute('app_admin_vehicule_index');
        }

        return $this->render('admin/vehicule/new.html.twig', [
            'form' => $form,
            'mode' => 'location',
        ]);
    }

    #[Route('/{id}/archiver', name: 'app_admin_vehicule_toggle_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleArchive(Vehicule $vehicule, EntityManagerInterface $entityManager, ActionLogger $actionLogger): Response
    {
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