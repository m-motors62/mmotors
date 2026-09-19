<?php

namespace App\Repository;

use App\Entity\Dossier;
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
            ->orderBy('v.dateCreation', 'DESC');

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

    /**
     * @return Vehicule[]
     */
    public function search(?string $mode = null, ?string $marque = null, ?string $modele = null, ?float $prixMax = null, ?int $kilometrageMax = null, ?string $motorisation = null): array
    {
        $qb = $this->baseFiltreQuery($mode, $marque, $modele, $prixMax, $kilometrageMax, $motorisation)
            ->leftJoin('v.vehiculePhotos', 'photos')
            ->addSelect('photos')
            ->orderBy('v.dateCreation', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @return int[]
     */
    private function findVehiculeIdsIndisponibles(): array
    {
        $result = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT v.id')
            ->from(Vehicule::class, 'v')
            ->innerJoin('App\Entity\Dossier', 'd', 'WITH', 'd.vehicule = v')
            ->andWhere('d.statut = :statut')
            ->setParameter('statut', 'valide')
            ->getQuery()
            ->getScalarResult();

        return array_map('intval', array_column($result, 'id'));
    }

    /**
     * @return string[]
     */
    public function getModesDisponibles(?string $marque = null, ?string $modele = null, ?string $motorisation = null): array
    {
        $qb = $this->baseFiltreQuery(null, $marque, $modele, null, null, $motorisation)
            ->select('DISTINCT v.statut')
            ->orderBy('v.statut', 'ASC');

        return array_column($qb->getQuery()->getScalarResult(), 'statut');
    }

    /**
     * @return string[]
     */
    public function getMarquesDisponibles(?string $modele = null, ?string $motorisation = null, ?string $mode = null): array
    {
        $qb = $this->baseFiltreQuery($mode, null, $modele, null, null, $motorisation)
            ->select('DISTINCT v.marque')
            ->orderBy('v.marque', 'ASC');

        return array_column($qb->getQuery()->getScalarResult(), 'marque');
    }

    /**
     * @return string[]
     */
    public function getModelesDisponibles(?string $marque = null, ?string $motorisation = null, ?string $mode = null): array
    {
        $qb = $this->baseFiltreQuery($mode, $marque, null, null, null, $motorisation)
            ->select('DISTINCT v.modele')
            ->orderBy('v.modele', 'ASC');

        return array_column($qb->getQuery()->getScalarResult(), 'modele');
    }

    /**
     * @return string[]
     */
    public function getMotorisationsDisponibles(?string $marque = null, ?string $modele = null, ?string $mode = null): array
    {
        $qb = $this->baseFiltreQuery($mode, $marque, $modele, null, null, null)
            ->select('DISTINCT v.motorisation')
            ->andWhere('v.motorisation IS NOT NULL')
            ->orderBy('v.motorisation', 'ASC');

        return array_column($qb->getQuery()->getScalarResult(), 'motorisation');
    }

    private function baseFiltreQuery(?string $mode, ?string $marque, ?string $modele, ?float $prixMax, ?int $kilometrageMax, ?string $motorisation): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('v')
            ->andWhere('v.isArchived = false')
            ->andWhere('v.id NOT IN (:vehiculeIdsIndisponibles)')
            ->setParameter('vehiculeIdsIndisponibles', $this->findVehiculeIdsIndisponibles() ?: [0]);

        if ($mode) {
            $qb->andWhere('v.statut = :mode')->setParameter('mode', $mode);
        }
        if ($marque) {
            $qb->andWhere('LOWER(v.marque) = LOWER(:marque)')->setParameter('marque', $marque);
        }
        if ($modele) {
            $qb->andWhere('LOWER(v.modele) = LOWER(:modele)')->setParameter('modele', $modele);
        }
        if ($prixMax) {
            $qb->andWhere('v.prix <= :prixMax')->setParameter('prixMax', $prixMax);
        }
        if ($kilometrageMax) {
            $qb->andWhere('v.kilometrage <= :kilometrageMax')->setParameter('kilometrageMax', $kilometrageMax);
        }
        if ($motorisation) {
            $qb->andWhere('LOWER(v.motorisation) = LOWER(:motorisation)')->setParameter('motorisation', $motorisation);
        }

        return $qb;
    }
}