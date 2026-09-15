<?php

namespace App\Service;

use App\Entity\ActionLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ActionLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function log(string $actionType, string $description, ?string $targetType = null, ?int $targetId = null): void
    {
        $log = new ActionLog();
        $log->setActionType($actionType);
        $log->setDescription($description);
        $log->setTargetType($targetType);
        $log->setTargetId($targetId);
        $log->setCreatedAt(new \DateTimeImmutable());

        $currentUser = $this->security->getUser();
        if ($currentUser instanceof User) {
            $log->setActor($currentUser);
        }

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}