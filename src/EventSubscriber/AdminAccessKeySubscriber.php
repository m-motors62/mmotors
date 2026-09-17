<?php

namespace App\EventSubscriber;

use App\Repository\AppSettingRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class AdminAccessKeySubscriber implements EventSubscriberInterface
{
    private const SESSION_KEY = 'admin_access_key_verified';

    public function __construct(private AppSettingRepository $appSettingRepository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/admin')) {
            return;
        }

        $session = $request->getSession();

        // Deja verifie sur cette session, on ne redemande pas la cle a chaque requete
        if ($session->get(self::SESSION_KEY)) {
            return;
        }

        $setting = $this->appSettingRepository->findOneBy(['settingKey' => 'admin_access_key']);
        $expectedKey = $setting?->getSettingValue();

        if (!$expectedKey) {
            // Pas de cle configuree : on n'active pas la restriction
            return;
        }

        $providedKey = $request->query->get('key');

        if ($providedKey === $expectedKey) {
            $session->set(self::SESSION_KEY, true);
            return;
        }

        throw new NotFoundHttpException();
    }
}