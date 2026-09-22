<?php

namespace App\Controller;

use App\Repository\ClientRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Client;
use App\Repository\DossierRepository;
use App\Service\ActionLogger;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/admin/clients')]
#[IsGranted('ROLE_COMMERCIAL')]
class AdminClientController extends AbstractController
{
    #[Route('', name: 'app_admin_client_index')]
    public function index(ClientRepository $clientRepository): Response
    {
        $clients = $clientRepository->findAll();

        return $this->render('admin/client/index.html.twig', [
            'clients' => $clients,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_client_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Client $client, EntityManagerInterface $entityManager, DossierRepository $dossierRepository, ActionLogger $actionLogger): Response
    {
        $dossierCount = $dossierRepository->count(['client' => $client]);

        if ($dossierCount > 0) {
            return $this->json([
                'success' => false,
                'message' => 'Ce client a au moins un dossier et ne peut pas etre supprime.',
            ], 422);
        }

        $contact = $client->getContact();
        $description = sprintf('A supprime le client %s %s (%s)', $contact->getPrenom(), $contact->getNom(), $contact->getEmail());

        $entityManager->remove($client);
        $entityManager->remove($contact);
        $entityManager->flush();

        $actionLogger->log('suppression_client', $description, 'Client', null);

        return $this->json(['success' => true]);
    }
}