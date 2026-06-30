<?php

namespace App\Scoring;

/**
 * Kent competition-rangen (1, 1, 3, …) toe aan een AL gesorteerde lijst: items met
 * dezelfde hoofdwaarde (binnen 0.0001, zodat floats geen schijnverschil geven) delen
 * een rang. Eén bron voor het klassement (web), de movement-posities en de API.
 */
final class Ranker
{
    private const EPSILON = 0.0001;

    /**
     * @template T
     *
     * @param list<T>                   $sorted
     * @param callable(T): (int|float)  $valueOf
     *
     * @return list<int> de rang per index, uitgelijnd op $sorted
     */
    public static function assign(array $sorted, callable $valueOf): array
    {
        $ranks = [];
        $rank = 0;
        $prev = null;
        foreach ($sorted as $i => $item) {
            $value = (float) $valueOf($item);
            if ($prev === null || abs($value - $prev) > self::EPSILON) {
                $rank = $i + 1;
                $prev = $value;
            }
            $ranks[] = $rank;
        }

        return $ranks;
    }
}
