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

            $activeMemberships = $this->activeMemberships($user);

            foreach ($activeMemberships as $pool) {
                if ($pool->isDefault()) {
                    return $pool;
                }
            }

            if ($activeMemberships !== []) {
                return $activeMemberships[0];
            }
            // Geen enkele actieve poule (wees): val terug op de globale standaardpoule
            // voor de weergave; de wees-melding wijst de speler erop.
        }

        return $this->pools->findDefault();
    }

    /**
     * @return list<Pool>
     */
    public function getSwitchablePools(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return $this->activeMemberships($user);
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

        return $this->activeMemberships($user) === [];
    }

    /**
     * Gebruiker-id's van de leden van de actieve poule, of null als er (nog) geen
     * poule is - dan blijft het klassement ongescoped (alle spelers).
     *
     * @return list<int>|null
     */
    public function getMemberIds(): ?array
    {
        $pool = $this->getActivePool();
        return $pool?->memberIds();
    }

    /**
     * @return list<Pool>
     */
    private function activeMemberships(User $user): array
    {
        $pools = [];
        foreach ($user->getPools() as $pool) {
            if (!$pool->isArchived()) {
                $pools[] = $pool;
            }
        }

        return $pools;
    }
}
