<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\Dossier;
use App\Entity\Vehicule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Dossier>
 */
class DossierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dossier::class);
    }

    public function hasActiveDossierFor(Client $client, Vehicule $vehicule): bool
    {
        $count = $this->count([
            'client' => $client,
            'vehicule' => $vehicule,
            'statut' => 'en_cours',
        ]);

        return $count > 0;
    }

    /**
     * @return Dossier[]
     */
    public function findByClient(Client $client): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.vehicule', 'vehicule')
            ->addSelect('vehicule')
            ->andWhere('d.client = :client')
            ->setParameter('client', $client)
            ->orderBy('d.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }
}