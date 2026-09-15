<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\ActionLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class)]
class LoginSuccessListener
{
    public function __construct(private ActionLogger $actionLogger)
    {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $this->actionLogger->log(
            'connexion',
            sprintf('Connexion de %s %s', $user->getContact()->getPrenom(), $user->getContact()->getNom()),
            'User',
            $user->getId()
        );
    }
}