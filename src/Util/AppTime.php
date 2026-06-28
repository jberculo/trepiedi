<?php

namespace App\Util;

/**
 * Eén bron van waarheid voor de tijdzone van de applicatie. De poule draait op
 * Nederlandse tijd: ingevoerde aftraptijden zijn wandtijd in Europe/Amsterdam.
 *
 * Zonder dit zou een ingevoerde "21:00" geïnterpreteerd worden in de tijdzone
 * van de server (op productie UTC), waardoor een wedstrijd pas twee uur te laat
 * als "begonnen" geldt en de voorspellingen onterecht verborgen blijven.
 */
final class AppTime
{
    public const ZONE = 'Europe/Amsterdam';

    public static function install(): void
    {
        date_default_timezone_set(self::ZONE);
    }

    public static function zone(): \DateTimeZone
    {
        return new \DateTimeZone(self::ZONE);
    }
}
