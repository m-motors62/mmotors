<?php

namespace App\Controller;

use App\Entity\Vehicule;
use App\Entity\VehiculePhoto;
use App\Form\VehiculeType;
use App\Repository\VehiculeRepository;
use App\Service\ActionLogger;
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
    public function index(VehiculeRepository $vehiculeRepository): Response
    {
        $vehicules = $vehiculeRepository->findAll();

        return $this->render('admin/vehicule/index.html.twig', [
            'vehicules' => $vehicules,
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
}