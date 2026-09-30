<?php

namespace App\Controller;

use App\Entity\PageStatique;
use App\Repository\PageStatiqueRepository;
use App\Service\ActionLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/pages')]
#[IsGranted('ROLE_ADMIN')]
class AdminPageStatiqueController extends AbstractController
{
    #[Route('', name: 'app_admin_page_statique_index')]
    public function index(PageStatiqueRepository $pageStatiqueRepository): Response
    {
        $pages = $pageStatiqueRepository->findAll();

        return $this->render('admin/page_statique/index.html.twig', [
            'pages' => $pages,
        ]);
    }

    #[Route('/{slug}/modifier', name: 'app_admin_page_statique_edit')]
    public function edit(string $slug, Request $request, PageStatiqueRepository $pageStatiqueRepository, EntityManagerInterface $entityManager, ActionLogger $actionLogger): Response
    {
        $page = $pageStatiqueRepository->findOneBy(['slug' => $slug]);

        if (!$page) {
            $page = new PageStatique();
            $page->setSlug($slug);
            $page->setTitre($slug === 'mentions-legales' ? 'Mentions legales' : 'Conditions generales de vente');
            $page->setContenu('');
        }

        if ($request->isMethod('POST')) {
            $page->setTitre($request->request->get('titre'));
            $page->setContenu($request->request->get('contenu'));
            $page->setDateModification(new \DateTimeImmutable());

            $entityManager->persist($page);
            $entityManager->flush();

            $actionLogger->log(
                'modification_page_statique',
                sprintf('A modifie la page "%s"', $page->getTitre()),
                'PageStatique',
                $page->getId()
            );

            $this->addFlash('success', 'Page mise a jour.');
            return $this->redirectToRoute('app_admin_page_statique_index');
        }

        return $this->render('admin/page_statique/edit.html.twig', [
            'page' => $page,
        ]);
    }
}