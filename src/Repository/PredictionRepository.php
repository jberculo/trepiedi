<?php

namespace App\Repository;

use App\Entity\FootballMatch;
use App\Entity\Prediction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Prediction>
 */
class PredictionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prediction::class);
    }

    public function findOneForUserAndMatch(User $user, FootballMatch $match): ?Prediction
    {
        return $this->findOneBy(['user' => $user, 'footballMatch' => $match]);
    }

    /**
     * Alle voorspellingen van een gebruiker, geïndexeerd op wedstrijd-id.
     *
     * @return array<int, Prediction>
     */
    public function findByUserIndexedByMatch(User $user): array
    {
        $result = [];
        foreach ($this->findBy(['user' => $user]) as $prediction) {
            $result[$prediction->getFootballMatch()->getId()] = $prediction;
        }

        return $result;
    }

    /**
     * Id's van alle spelers die minstens één voorspelling hebben gedaan.
     *
     * @return list<int>
     */
    public function userIdsWithPredictions(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT IDENTITY(p.user) AS uid')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['uid'], $rows);
    }

    /**
     * Alle voorspellingen voor één wedstrijd, met speler.
     *
     * @return list<Prediction>
     */
    public function findByMatch(FootballMatch $match): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.user', 'u')->addSelect('u')
            ->where('p.footballMatch = :match')->setParameter('match', $match)
            ->orderBy('u.displayName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Alle voorspellingen voor afgeronde wedstrijden, met user, wedstrijd en ronde.
     *
     * @return list<Prediction>
     */
    public function findAllForScoring(): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.user', 'u')->addSelect('u')
            ->join('p.footballMatch', 'm')->addSelect('m')
            ->join('m.round', 'r')->addSelect('r')
            ->where('m.finished = true')
            ->getQuery()
            ->getResult();
    }
}
