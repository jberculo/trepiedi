<?php

namespace App\Pool;

use App\Repository\PoolRepository;
use App\Util\Slug;

/**
 * Genereert een inschrijfcode uit de poulenaam plus een korte salt, bijv.
 * "Het Kantoor" → "het-kantoor-7b2e". Leesbaar, niet triviaal te raden, en uniek.
 */
class PoolCodeGenerator
{
    /** Maximale lengte van de code-kolom. */
    private const MAX_LENGTH = 32;

    public function __construct(private PoolRepository $pools)
    {
    }

    public function generate(string $name): string
    {
        $base = Slug::make($name);
        // Ruimte laten voor "-" + 4 salt-tekens binnen de kolomlengte.
        $base = substr($base, 0, self::MAX_LENGTH - 5);

        do {
            $salt = substr(bin2hex(random_bytes(2)), 0, 4);
            $code = $base . '-' . $salt;
        } while ($this->pools->findOneByCode($code) !== null);

        return $code;
    }
}
