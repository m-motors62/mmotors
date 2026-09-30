<?php

namespace App\Controller;

use App\Repository\PageStatiqueRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PageStatiqueController extends AbstractController
{
    #[Route('/mentions-legales', name: 'app_page_mentions_legales')]
    public function mentionsLegales(PageStatiqueRepository $pageStatiqueRepository): Response
    {
        return $this->afficherPage('mentions-legales', $pageStatiqueRepository);
    }

    #[Route('/cgv', name: 'app_page_cgv')]
    public function cgv(PageStatiqueRepository $pageStatiqueRepository): Response
    {
        return $this->afficherPage('cgv', $pageStatiqueRepository);
    }

    private function afficherPage(string $slug, PageStatiqueRepository $pageStatiqueRepository): Response
    {
        $page = $pageStatiqueRepository->findOneBy(['slug' => $slug]);

        if (!$page) {
            throw $this->createNotFoundException('Cette page n\'a pas encore ete redigee.');
        }

        return $this->render('page_statique/show.html.twig', [
            'page' => $page,
        ]);
    }
}