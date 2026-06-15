<?php

namespace App\Repository;

use App\Entity\Round;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Round>
 */
class RoundRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Round::class);
    }

    /**
     * Alle ronden in speelvolgorde, met wedstrijden eager geladen.
     *
     * @return list<Round>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.matches', 'm')->addSelect('m')
            ->orderBy('r.sortOrder', 'ASC')
            ->addOrderBy('m.kickoffAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
