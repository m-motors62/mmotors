<?php

namespace App\Controller;

use App\Entity\MotifRefus;
use App\Entity\User;
use App\Form\MotifRefusType;
use App\Repository\MotifRefusRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/motifs-refus')]
#[IsGranted('ROLE_COMMERCIAL')]
class AdminMotifRefusController extends AbstractController
{
    #[Route('', name: 'app_admin_motif_refus_index')]
    public function index(MotifRefusRepository $motifRefusRepository): Response
    {
        $motifs = $motifRefusRepository->findBy([], ['contexte' => 'ASC', 'libelle' => 'ASC']);

        return $this->render('admin/motif_refus/index.html.twig', [
            'motifs' => $motifs,
        ]);
    }

    #[Route('/nouveau', name: 'app_admin_motif_refus_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $motif = new MotifRefus();
        $form = $this->createForm(MotifRefusType::class, $motif);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $motif->setDateCreation(new \DateTimeImmutable());

            /** @var User $currentUser */
            $currentUser = $this->getUser();
            $motif->setCreePar($currentUser);

            $entityManager->persist($motif);
            $entityManager->flush();

            $this->addFlash('success', 'Motif cree avec succes.');
            return $this->redirectToRoute('app_admin_motif_refus_index');
        }

        return $this->render('admin/motif_refus/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_motif_refus_edit', requirements: ['id' => '\d+'])]
    public function edit(MotifRefus $motif, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MotifRefusType::class, $motif);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Motif modifie avec succes.');
            return $this->redirectToRoute('app_admin_motif_refus_index');
        }

        return $this->render('admin/motif_refus/edit.html.twig', [
            'form' => $form,
            'motif' => $motif,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_motif_refus_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(MotifRefus $motif, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($motif);
        $entityManager->flush();

        return $this->json(['success' => true]);
    }
}