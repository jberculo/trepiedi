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

    public function findOneByCode(string $code): ?Pool
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * De standaardpoule (waarin spelers zonder code belanden), of null als die er
     * nog niet is.
     */
    public function findDefault(): ?Pool
    {
        return $this->findOneBy(['isDefault' => true]);
    }
}
