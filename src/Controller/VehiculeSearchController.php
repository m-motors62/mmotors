<?php

namespace App\Controller;

use App\Repository\VehiculeRepository;
use App\Repository\DossierRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VehiculeSearchController extends AbstractController
{
    #[Route('/vehicules', name: 'app_vehicule_search')]
    #[Route('/vehicules/{mode}', name: 'app_vehicule_search_mode', requirements: ['mode' => 'location|vente'])]
    #[Route('/vehicules/{mode}/{marque}', name: 'app_vehicule_search_marque', requirements: ['mode' => 'location|vente'])]
    #[Route('/vehicules/{mode}/{marque}/{modele}', name: 'app_vehicule_search_modele', requirements: ['mode' => 'location|vente'])]
    public function index(Request $request, VehiculeRepository $vehiculeRepository, ?string $mode = null, ?string $marque = null, ?string $modele = null): Response
    {
        $mode = $mode ?: $request->query->get('mode');
        $marque = $marque ?: $request->query->get('marque');
        $modele = $modele ?: $request->query->get('modele');
        $prixMax = $request->query->get('prix_max');
        $kilometrageMax = $request->query->get('kilometrage_max');
        $motorisation = $request->query->get('motorisation');

        $vehicules = $vehiculeRepository->search(
            mode: $mode,
            marque: $marque,
            modele: $modele,
            prixMax: $prixMax ? (float) $prixMax : null,
            kilometrageMax: $kilometrageMax ? (int) $kilometrageMax : null,
            motorisation: $motorisation,
        );

        return $this->render('vehicule_search/index.html.twig', [
            'vehicules' => $vehicules,
            'mode' => $mode,
            'marque' => $marque,
            'modele' => $modele,
            'filtres' => [
                'prix_max' => $prixMax,
                'kilometrage_max' => $kilometrageMax,
                'motorisation' => $motorisation,
            ],
            'modesDisponibles' => $vehiculeRepository->getModesDisponibles($marque, $modele, $motorisation),
            'marquesDisponibles' => $vehiculeRepository->getMarquesDisponibles($modele, $motorisation, $mode),
            'modelesDisponibles' => $vehiculeRepository->getModelesDisponibles($marque, $motorisation, $mode),
            'motorisationsDisponibles' => $vehiculeRepository->getMotorisationsDisponibles($marque, $modele, $mode),
        ]);
    }

    #[Route('/vehicule/{slug}', name: 'app_vehicule_show', requirements: ['slug' => '.+-\d+'])]
    public function show(string $slug, VehiculeRepository $vehiculeRepository, DossierRepository $dossierRepository): Response
    {
        preg_match('/(\d+)$/', $slug, $matches);
        $id = $matches[1] ?? null;

        $vehicule = $id ? $vehiculeRepository->find($id) : null;

        if (!$vehicule || $vehicule->isArchived()) {
            throw $this->createNotFoundException();
        }

        $dejaUnDossierActif = false;
        if ($this->getUser() instanceof \App\Entity\Client) {
            $dejaUnDossierActif = $dossierRepository->hasActiveDossierFor($this->getUser(), $vehicule);
        }

        return $this->render('vehicule_search/show.html.twig', [
            'vehicule' => $vehicule,
            'dejaUnDossierActif' => $dejaUnDossierActif,
        ]);
    }

    #[Route('/vehicule/{slug}/deposer-dossier', name: 'app_vehicule_deposer_dossier', requirements: ['slug' => '.+-\d+'])]
    public function redirectToDeposit(string $slug, Request $request, VehiculeRepository $vehiculeRepository): Response
    {
        preg_match('/(\d+)$/', $slug, $matches);
        $id = $matches[1] ?? null;

        $vehicule = $id ? $vehiculeRepository->find($id) : null;

        if (!$vehicule || $vehicule->isArchived()) {
            throw $this->createNotFoundException();
        }

        if (!$this->getUser()) {
            $request->getSession()->set('vehicule_intention_id', $vehicule->getId());
            return $this->redirectToRoute('app_client_login');
        }

        return $this->redirectToRoute('app_dossier_new', ['vehiculeId' => $vehicule->getId()]);
    }
}