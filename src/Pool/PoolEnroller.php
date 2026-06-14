<?php

namespace App\Pool;

use App\Entity\User;
use App\Repository\PoolRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Schrijft een speler in op een poule (op basis van een code, anders de
 * standaardpoule) en maakt die meteen de actieve poule. Wordt gebruikt bij
 * registratie en — voor een gestashte uitnodigingscode — bij inloggen.
 */
class PoolEnroller
{
    /** Sessiesleutel voor een uitnodigingscode die nog verzilverd moet worden. */
    public const SESSION_KEY = 'pool_code';

    public function __construct(
        private PoolRepository $pools,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Schrijf in op de poule van de code; valt terug op de standaardpoule als de
     * code leeg/onbekend is. Maakt de poule actief en bewaart. Geeft de poule terug.
     */
    public function enroll(User $user, ?string $code): ?\App\Entity\Pool
    {
        $pool = ($code !== null && $code !== '') ? $this->pools->findOneByCode($code) : null;
        $pool ??= $this->pools->findDefault();
        if ($pool === null) {
            return null;
        }

        $user->addPool($pool);
        $user->setActivePool($pool);
        $this->em->flush();

        return $pool;
    }
}
