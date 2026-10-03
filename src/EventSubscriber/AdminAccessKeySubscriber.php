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
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/admin')) {
            return;
        }

        // Routes exemptees : protegees par leur propre mecanisme (token unique envoye par email)
        if (str_starts_with($path, '/admin/reinitialiser')) {
            return;
        }

        $session = $request->getSession();

        if ($session->get(self::SESSION_KEY)) {
            return;
        }

        $setting = $this->appSettingRepository->findOneBy(['settingKey' => 'admin_access_key']);
        $expectedKey = $setting?->getSettingValue();

        if (!$expectedKey) {
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