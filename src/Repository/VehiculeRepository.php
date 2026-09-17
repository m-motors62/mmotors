<?php

namespace App\Repository;

use App\Entity\Vehicule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vehicule>
 */
class VehiculeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vehicule::class);
    }

    /**
     * @return Vehicule[]
     */
    public function findAllFiltered(bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('v')
            ->leftJoin('v.vehiculePhotos', 'photos')
            ->addSelect('photos')
            ->orderBy('v.id', 'DESC');

        if (!$includeArchived) {
            $qb->andWhere('v.isArchived = false');
        }

        return $qb->getQuery()->getResult();
    }
}