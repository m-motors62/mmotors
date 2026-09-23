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
        $adminAccessKeySetting = $this->getOrCreateSetting($appSettingRepository, 'admin_access_key', '');
        $maxFileSizeSetting = $this->getOrCreateSetting($appSettingRepository, 'max_file_size_mo', '5');

        $form = $this->createForm(AppSettingType::class, null);
        $form->get('adminAccessKey')->setData($adminAccessKeySetting->getSettingValue());
        $form->get('maxFileSizeMo')->setData((int) $maxFileSizeSetting->getSettingValue());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $adminAccessKeySetting->setSettingValue($form->get('adminAccessKey')->getData());
            $maxFileSizeSetting->setSettingValue((string) $form->get('maxFileSizeMo')->getData());

            $entityManager->persist($adminAccessKeySetting);
            $entityManager->persist($maxFileSizeSetting);
            $entityManager->flush();

            $actionLogger->log(
                'modification_parametrage',
                'A modifie les parametres globaux (cle d\'acces admin, taille max fichiers)',
                'AppSetting',
                null
            );

            $this->addFlash('success', 'Parametres mis a jour.');
            return $this->redirectToRoute('app_admin_setting_index');
        }

        return $this->render('admin/setting/index.html.twig', [
            'form' => $form,
        ]);
    }

    private function getOrCreateSetting(AppSettingRepository $repository, string $key, string $defaultValue): AppSetting
    {
        $setting = $repository->findOneBy(['settingKey' => $key]);

        if (!$setting) {
            $setting = new AppSetting();
            $setting->setSettingKey($key);
            $setting->setSettingValue($defaultValue);
        }

        return $setting;
    }
}