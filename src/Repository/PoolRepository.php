<?php

namespace App\Repository;

use App\Entity\Pool;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pool>
 */
class PoolRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pool::class);
    }

    /**
     * Een poule op code — ook gearchiveerde (voor beheer/herstel).
     */
    public function findOneByCode(string $code): ?Pool
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * Een actieve (niet-gearchiveerde) poule op code — voor inschrijven en wisselen.
     */
    public function findOneActiveByCode(string $code): ?Pool
    {
        return $this->findOneBy(['code' => $code, 'archivedAt' => null]);
    }

    /**
     * De (actieve) standaardpoule waarin spelers zonder code belanden, of null.
     */
    public function findDefault(): ?Pool
    {
        return $this->findOneBy(['isDefault' => true, 'archivedAt' => null]);
    }

    /**
     * Alle poules voor het beheer: actieve eerst, daarna gearchiveerde.
     *
     * @return list<Pool>
     */
    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.archivedAt', 'ASC') // NULL (actief) eerst op MySQL/MariaDB
            ->addOrderBy('p.isDefault', 'DESC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
