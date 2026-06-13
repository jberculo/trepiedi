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
     * Alle ronden in speelvolgorde, met wedstrijden en teams eager geladen.
     *
     * @return list<Round>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.matches', 'm')->addSelect('m')
            ->leftJoin('m.homeTeam', 'h')->addSelect('h')
            ->leftJoin('m.awayTeam', 'a')->addSelect('a')
            ->orderBy('r.sortOrder', 'DESC')
            ->addOrderBy('m.kickoffAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
