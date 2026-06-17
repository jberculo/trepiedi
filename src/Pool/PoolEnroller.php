<?php

namespace App\Pool;

use App\Entity\Pool;
use App\Entity\User;
use App\Repository\PoolRepository;

/**
 * Schrijft een speler in op een poule (op basis van een code, anders de
 * standaardpoule) en maakt die meteen de actieve poule. Wordt gebruikt bij
 * registratie en - voor een gestashte uitnodigingscode - bij inloggen.
 */
class PoolEnroller
{
    /** Sessiesleutel voor een uitnodigingscode die nog verzilverd moet worden. */
    public const SESSION_KEY = 'pool_code';

    public function __construct(private PoolRepository $pools)
    {
    }

    /**
     * Schrijf in op de actieve poule van de code, of (zonder code) op de
     * standaardpoule. Een foutieve/gearchiveerde code schrijft NIET stilletjes in
     * op de standaardpoule maar levert null op. Maakt de poule actief; flushen
     * blijft de verantwoordelijkheid van de caller.
     */
    public function enroll(User $user, ?string $code): ?Pool
    {
        if ($code !== null && $code !== '') {
            $pool = $this->pools->findOneActiveByCode($code);
        } else {
            $pool = $this->pools->findDefault();
        }

        if ($pool === null) {
            return null;
        }

        $user->addPool($pool);
        $user->setActivePool($pool);

        return $pool;
    }

    /**
     * Is dit een geldige (actieve) inschrijfcode? Lege code telt als geldig
     * (= standaardpoule).
     */
    public function isValidCode(?string $code): bool
    {
        if ($code === null || $code === '') {
            return true;
        }

        return $this->pools->findOneActiveByCode($code) !== null;
    }
}
