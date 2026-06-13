<?php

namespace App\Repository;

use App\Entity\FootballMatch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FootballMatch>
 */
class FootballMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FootballMatch::class);
    }

    /**
     * Alle wedstrijden in chronologische volgorde, met ronde en teams.
     *
     * @return list<FootballMatch>
     */
    public function findAllForOverview(): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.round', 'r')->addSelect('r')
            ->leftJoin('m.homeTeam', 'h')->addSelect('h')
            ->leftJoin('m.awayTeam', 'a')->addSelect('a')
            ->orderBy('r.sortOrder', 'DESC')
            ->addOrderBy('m.kickoffAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
