<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminDashboardController extends AbstractController
{
    #[Route('', name: 'app_admin_root')]
    public function root(): Response
    {
        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function index(): Response
    {
        return $this->render('admin/dashboard/index.html.twig');
    }
}