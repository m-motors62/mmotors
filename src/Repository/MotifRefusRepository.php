<?php

namespace App\Repository;

use App\Entity\MotifRefus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MotifRefus>
 */
class MotifRefusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MotifRefus::class);
    }

    /**
     * @return MotifRefus[]
     */
    public function findForContext(string $contexte, ?string $typeDocument = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.contexte = :contexte')
            ->setParameter('contexte', $contexte)
            ->orderBy('m.libelle', 'ASC');

        if ($typeDocument) {
            $qb->andWhere('m.typeDocument = :type OR m.typeDocument IS NULL')
                ->setParameter('type', $typeDocument);
        }

        return $qb->getQuery()->getResult();
    }
}
