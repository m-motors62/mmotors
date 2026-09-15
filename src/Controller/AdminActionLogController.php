<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ActionLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/journal')]
class AdminActionLogController extends AbstractController
{
    #[Route('', name: 'app_admin_action_log_index')]
    public function index(ActionLogRepository $actionLogRepository): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $logs = $actionLogRepository->findVisibleFor($currentUser);

        return $this->render('admin/action_log/index.html.twig', [
            'logs' => $logs,
        ]);
    }
}