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

    /**
     * @return int[] IDs des vehicules ayant un dossier actif (en_cours ou valide)
     */
    public function findVehiculeIdsWithActiveDossier(): array
    {
        $result = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT v.id')
            ->from(Vehicule::class, 'v')
            ->innerJoin('App\Entity\Dossier', 'd', 'WITH', 'd.vehicule = v')
            ->andWhere('d.statut IN (:statuts)')
            ->setParameter('statuts', ['en_cours', 'valide'])
            ->getQuery()
            ->getScalarResult();

        return array_map('intval', array_column($result, 'id'));
    }
}