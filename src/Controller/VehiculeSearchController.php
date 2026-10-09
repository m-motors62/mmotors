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

        $prix = $prixMax ? (float) $prixMax : null;
        $km = $kilometrageMax ? (int) $kilometrageMax : null;

        $vehicules = $vehiculeRepository->search(
            mode: $mode,
            marque: $marque,
            modele: $modele,
            prixMax: $prix,
            kilometrageMax: $km,
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
            'modesDisponibles' => $this->avecSelection(
                $vehiculeRepository->getModesDisponibles($marque, $modele, $motorisation, $prix, $km),
                in_array($mode, ['location', 'vente'], true) ? $mode : null
            ),
            'marquesDisponibles' => $this->avecSelection($vehiculeRepository->getMarquesDisponibles($modele, $motorisation, $mode, $prix, $km), $marque),
            'modelesDisponibles' => $this->avecSelection($vehiculeRepository->getModelesDisponibles($marque, $motorisation, $mode, $prix, $km), $modele),
            'motorisationsDisponibles' => $this->avecSelection($vehiculeRepository->getMotorisationsDisponibles($marque, $modele, $mode, $prix, $km), $motorisation),
        ]);
    }

    /**
     * Garde la valeur choisie dans sa liste, même si les autres filtres l'excluent :
     * sans cela, un filtre sans résultat viderait les listes et semblerait effacer la sélection.
     *
     * @param string[] $liste
     * @return string[]
     */
    private function avecSelection(array $liste, ?string $selection): array
    {
        if (!$selection) {
            return $liste;
        }

        foreach ($liste as $valeur) {
            if (mb_strtolower((string) $valeur) === mb_strtolower($selection)) {
                return $liste;
            }
        }

        $liste[] = $selection;
        sort($liste, SORT_STRING | SORT_FLAG_CASE);

        return $liste;
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