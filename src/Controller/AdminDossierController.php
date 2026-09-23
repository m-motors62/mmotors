<?php

namespace App\Controller;

use App\Entity\Dossier;
use App\Service\ActionLogger;
use App\Repository\ActionLogRepository;
use App\Repository\DossierRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
}