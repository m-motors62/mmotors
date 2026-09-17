<?php

namespace App\Controller;

use App\Entity\AppSetting;
use App\Form\AppSettingType;
use App\Repository\AppSettingRepository;
use App\Service\ActionLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/parametrage')]
#[IsGranted('ROLE_ADMIN')]
class AdminSettingController extends AbstractController
{
    #[Route('', name: 'app_admin_setting_index')]
    public function index(Request $request, AppSettingRepository $appSettingRepository, EntityManagerInterface $entityManager, ActionLogger $actionLogger): Response
    {
        $setting = $appSettingRepository->findOneBy(['settingKey' => 'admin_access_key']);

        if (!$setting) {
            $setting = new AppSetting();
            $setting->setSettingKey('admin_access_key');
            $setting->setSettingValue('');
        }

        $form = $this->createForm(AppSettingType::class, $setting);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($setting);
            $entityManager->flush();

            $actionLogger->log(
                'modification_parametrage',
                'A modifie la cle d\'acces a l\'espace admin',
                'AppSetting',
                $setting->getId()
            );

            $this->addFlash('success', 'Cle d\'acces mise a jour. Pensez a la communiquer aux personnes autorisees.');
            return $this->redirectToRoute('app_admin_setting_index');
        }

        return $this->render('admin/setting/index.html.twig', [
            'form' => $form,
        ]);
    }
}