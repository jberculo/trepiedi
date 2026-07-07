<?php

namespace App\Repository;

use App\Entity\FootballMatch;
use App\Entity\User;
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
     * Aantal nog te voorspellen wedstrijden voor een speler: actief, nog niet begonnen
     * (aftrap in de toekomst) en zonder voorspelling van deze speler.
     */
    public function countOpenWithoutPredictionForUser(User $user, \DateTimeImmutable $now): int
    {
        $qb = $this->createQueryBuilder('m');

        return (int) $qb
            ->select('COUNT(m.id)')
            ->where('m.active = true')
            ->andWhere('m.kickoffAt > :now')
            ->andWhere($qb->expr()->not(
                $qb->expr()->exists('SELECT p2.id FROM App\Entity\Prediction p2 WHERE p2.footballMatch = m AND p2.user = :user')
            ))
            ->setParameter('now', $now)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Alle wedstrijden in chronologische volgorde, met ronde.
     *
     * @return list<FootballMatch>
     */
    public function findAllForOverview(): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.round', 'r')->addSelect('r')
            ->orderBy('r.sortOrder', 'ASC')
            ->addOrderBy('m.kickoffAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Alle wedstrijden chronologisch (oudste eerst), met ronde — voor de publieke uitslagenpagina.
     *
     * @return list<FootballMatch>
     */
    public function findAllChronological(): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.round', 'r')->addSelect('r')
            ->orderBy('m.kickoffAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Alle eerder ingevoerde ploegnamen (thuis + uit), uniek en alfabetisch —
     * voor autocomplete bij het invoeren van wedstrijden.
     *
     * @return list<string>
     */
    public function distinctTeamNames(): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('m.homeTeam AS home, m.awayTeam AS away')
            ->getQuery()
            ->getScalarResult();

        $names = [];
        foreach ($rows as $row) {
            foreach ([$row['home'], $row['away']] as $name) {
                if ($name !== null && $name !== '') {
                    $names[$name] = true;
                }
            }
        }

        $names = array_keys($names);
        sort($names);

        return $names;
    }

    public function findOneLocked(): ?FootballMatch
    {
        return $this->createQueryBuilder('m')
            ->where('m.kickoffAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('m.kickoffAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneOpen(): ?FootballMatch
    {
        // "Open" = te voorspellen: actief én nog niet afgetrapt.
        return $this->createQueryBuilder('m')
            ->where('m.kickoffAt > :now')
            ->andWhere('m.active = :active')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('active', true)
            ->orderBy('m.kickoffAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
