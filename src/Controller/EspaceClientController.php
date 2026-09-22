<?php

namespace App\Controller;

use App\Entity\Client;
use App\Form\ClientProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/espace-client')]
#[IsGranted('ROLE_CLIENT')]
class EspaceClientController extends AbstractController
{
    #[Route('', name: 'app_espace_client_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('espace_client/dashboard.html.twig');
    }

    #[Route('/profil', name: 'app_espace_client_profile')]
    public function profile(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var Client $client */
        $client = $this->getUser();
        $contact = $client->getContact();

        $form = $this->createForm(ClientProfileType::class);
        $form->get('nom')->setData($contact->getNom());
        $form->get('prenom')->setData($contact->getPrenom());
        $form->get('telephone')->setData($contact->getTelephone());
        $form->get('adresse')->setData($contact->getAdresse());
        $form->get('complementAdresse')->setData($contact->getComplementAdresse());
        $form->get('codePostal')->setData($contact->getCodePostal());
        $form->get('ville')->setData($contact->getVille());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contact->setNom($form->get('nom')->getData());
            $contact->setPrenom($form->get('prenom')->getData());
            $contact->setTelephone($form->get('telephone')->getData());
            $contact->setAdresse($form->get('adresse')->getData());
            $contact->setComplementAdresse($form->get('complementAdresse')->getData());
            $contact->setCodePostal($form->get('codePostal')->getData());
            $contact->setVille($form->get('ville')->getData());
            $entityManager->flush();

            $this->addFlash('success', 'Vos informations ont ete mises a jour.');
            return $this->redirectToRoute('app_espace_client_profile');
        }

        return $this->render('espace_client/profile.html.twig', [
            'form' => $form,
        ]);
    }
}