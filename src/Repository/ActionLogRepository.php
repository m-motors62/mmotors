<?php

namespace App\Repository;

use App\Entity\ActionLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActionLog>
 */
class ActionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActionLog::class);
    }

    private const USER_ACTION_TYPES = [
        'connexion',
        'creation_utilisateur',
        'modification_utilisateur',
        'desactivation_utilisateur',
        'reactivation_utilisateur',
        'renvoi_lien',
    ];

    /**
     * @return ActionLog[]
     */
    public function findVisibleFor(User $currentUser): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.actor', 'actor')
            ->addSelect('actor')
            ->orderBy('a.createdAt', 'DESC');

        $isAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles(), true);
        $isGestionnaire = in_array('ROLE_GESTIONNAIRE', $currentUser->getRoles(), true);
        $isCommercial = in_array('ROLE_COMMERCIAL', $currentUser->getRoles(), true);

        if ($isAdmin) {
            // Aucune restriction
            return $qb->getQuery()->getResult();
        }

        if ($isGestionnaire) {
            $qb->andWhere('a.actionType IN (:userTypes)')
                ->setParameter('userTypes', self::USER_ACTION_TYPES)
                ->andWhere('actor.roles NOT LIKE :adminRole')
                ->setParameter('adminRole', '%ROLE_ADMIN%');

            return $qb->getQuery()->getResult();
        }

        if ($isCommercial) {
            $qb->andWhere('a.actionType NOT IN (:userTypes)')
                ->setParameter('userTypes', self::USER_ACTION_TYPES);

            return $qb->getQuery()->getResult();
        }

        return [];
    }
}