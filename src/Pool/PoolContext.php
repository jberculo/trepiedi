<?php

namespace App\Pool;

use App\Entity\Pool;
use App\Entity\User;
use App\Repository\PoolRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Bepaalt welke poule (en dus welk klassement) de huidige bezoeker ziet:
 *   - ingelogd: de gekozen activePool, anders de standaardpoule waarvan hij lid is,
 *     anders zijn eerste lidmaatschap;
 *   - uitgelogd: de standaardpoule.
 * Levert ook de gebruiker-id's van de poulleden, om het klassement te scopen.
 */
class PoolContext
{
    public function __construct(
        private Security $security,
        private PoolRepository $pools,
    ) {
    }

    public function getActivePool(): ?Pool
    {
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $active = $user->getActivePool();
            if ($active !== null && !$active->isArchived() && $user->isInPool($active)) {
                return $active;
            }

            // Standaardpoule onder de niet-gearchiveerde lidmaatschappen.
            foreach ($user->getPools() as $pool) {
                if (!$pool->isArchived() && $pool->isDefault()) {
                    return $pool;
                }
            }

            // Anders het eerste actieve lidmaatschap.
            foreach ($user->getPools() as $pool) {
                if (!$pool->isArchived()) {
                    return $pool;
                }
            }
            // Geen enkele actieve poule (wees): val terug op de globale standaardpoule
            // voor de weergave; de wees-melding wijst de speler erop.
        }

        return $this->pools->findDefault();
    }

    /**
     * Een ingelogde speler die in geen enkele (niet-gearchiveerde) poule meer zit.
     */
    public function currentUserIsOrphan(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }

        foreach ($user->getPools() as $pool) {
            if (!$pool->isArchived()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gebruiker-id's van de leden van de actieve poule, of null als er (nog) geen
     * poule is — dan blijft het klassement ongescoped (alle spelers).
     *
     * @return list<int>|null
     */
    public function getMemberIds(): ?array
    {
        $pool = $this->getActivePool();
        if ($pool === null) {
            return null;
        }

        $ids = [];
        foreach ($pool->getMembers() as $member) {
            if ($member->getId() !== null) {
                $ids[] = $member->getId();
            }
        }

        return $ids;
    }
}
