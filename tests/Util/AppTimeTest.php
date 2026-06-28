<?php

namespace App\Tests\Util;

use App\Entity\FootballMatch;
use App\Util\AppTime;
use PHPUnit\Framework\TestCase;

/**
 * Reproduceert de productiebug: op een server die in UTC draait werd een
 * ingevoerde aftraptijd als UTC geïnterpreteerd in plaats van als Amsterdamse
 * tijd. Daardoor sloot een wedstrijd pas twee uur te laat en bleven de
 * voorspellingen onterecht verborgen.
 */
class AppTimeTest extends TestCase
{
    private string $originalTimezone;

    protected function setUp(): void
    {
        $this->originalTimezone = date_default_timezone_get();
        // Doe alsof we op de productieserver (UTC) draaien.
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTimezone);
    }

    public function testWallClockKickoffIsAmsterdamTimeOnAUtcServer(): void
    {
        AppTime::install();

        // De beheerder voert "21:00" in; dat hoort 21:00 Amsterdamse tijd te zijn
        // (= 19:00 UTC in de zomer), niet 21:00 UTC.
        $kickoff = new \DateTimeImmutable('2026-06-28 21:00');

        $this->assertSame(
            '2026-06-28 19:00',
            $kickoff->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i'),
            'Een ingevoerde aftraptijd hoort Amsterdamse wandtijd te zijn.'
        );
    }

    public function testStartedMatchIsLockedSoPredictionsBecomeVisible(): void
    {
        AppTime::install();

        // Aftrap gepland op 22:00 Amsterdamse tijd; "nu" is 22:30 Amsterdamse tijd,
        // dus de wedstrijd is al een half uur bezig en hoort gesloten te zijn.
        $now = new \DateTimeImmutable('2026-06-28 22:30', new \DateTimeZone('Europe/Amsterdam'));
        $match = (new FootballMatch())->setKickoffAt(new \DateTimeImmutable('2026-06-28 22:00'));

        // Zonder de fix werd de aftrap als 22:00 UTC (00:00 Amsterdam) gelezen en
        // gold de wedstrijd om 22:30 Amsterdam nog als "niet begonnen".
        $this->assertTrue(
            $match->isLocked($now),
            'Een afgetrapte wedstrijd hoort gesloten te zijn, zodat de voorspellingen zichtbaar worden.'
        );
    }
}
