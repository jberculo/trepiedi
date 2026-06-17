<?php

namespace App\Tests\Api;

use App\Api\ApiNormalizer;
use App\Entity\FootballMatch;
use App\Entity\User;
use App\Flag\FlagProvider;
use App\Scoring\LeaderboardEntry;
use PHPUnit\Framework\TestCase;

/**
 * Presentatie-mapping van domeinobjecten naar de API-data, gedeeld door REST en MCP.
 */
class ApiNormalizerTest extends TestCase
{
    private ApiNormalizer $normalizer;

    protected function setUp(): void
    {
        // Echte FlagProvider op de gebundelde vlaggen, zodat flagSvgs() echte SVG's leest.
        $flags = new FlagProvider(dirname(__DIR__, 2) . '/assets/flags');
        $this->normalizer = new ApiNormalizer($flags);
    }

    public function testMatchMapsFlagsAndPredictableForActiveOpenMatch(): void
    {
        $match = (new FootballMatch())
            ->setHomeTeam('Nederland')
            ->setAwayTeam('Brazilië')
            ->setKickoffAt(new \DateTimeImmutable('+2 days'))
            ->setActive(true);

        $data = $this->normalizer->match($match);

        $this->assertSame('Nederland', $data['home']);
        $this->assertSame('nl', $data['homeFlag']);
        $this->assertSame('br', $data['awayFlag']);
        $this->assertFalse($data['finished']);
        $this->assertTrue($data['open']);
        $this->assertFalse($data['locked']);
        $this->assertTrue($data['predictable'], 'Actief en niet vergrendeld → te voorspellen.');
    }

    public function testMatchOfFinishedGameIsNotOpenNorPredictable(): void
    {
        $match = (new FootballMatch())
            ->setHomeTeam('Nederland')
            ->setAwayTeam('Brazilië')
            ->setKickoffAt(new \DateTimeImmutable('-2 days'))
            ->setHomeScore(2)
            ->setAwayScore(1)
            ->setAdvancingSide(FootballMatch::SIDE_HOME)
            ->setFinished(true)
            ->setActive(true);

        $data = $this->normalizer->match($match);

        $this->assertTrue($data['finished']);
        $this->assertFalse($data['open']);
        $this->assertTrue($data['locked'], 'Aftrap in het verleden → vergrendeld.');
        $this->assertFalse($data['predictable']);
        $this->assertSame('Nederland', $data['advancingTeam']);
        $this->assertSame('nl', $data['advancingFlag']);
    }

    public function testMatchInactiveIsNotPredictable(): void
    {
        $match = (new FootballMatch())
            ->setHomeTeam('Nederland')
            ->setAwayTeam('Brazilië')
            ->setKickoffAt(new \DateTimeImmutable('+2 days'))
            ->setActive(false);

        $this->assertFalse($this->normalizer->match($match)['predictable']);
    }

    public function testFlagSvgsAreDedupedAndContainSvgMarkup(): void
    {
        $matches = [
            ['homeFlag' => 'nl', 'awayFlag' => 'br', 'advancingFlag' => 'nl'],
            ['homeFlag' => 'nl', 'awayFlag' => 'de', 'advancingFlag' => null],
            ['homeFlag' => 'zz', 'awayFlag' => null, 'advancingFlag' => null],
        ];

        $svgs = $this->normalizer->flagSvgs($matches);

        $this->assertArrayHasKey('nl', $svgs);
        $this->assertArrayHasKey('br', $svgs);
        $this->assertArrayHasKey('de', $svgs);
        $this->assertArrayNotHasKey('zz', $svgs, 'Onbekende code levert geen SVG.');
        $this->assertStringContainsString('<svg', $svgs['nl']);
    }

    public function testRankingTypesAreTheSingleSourceOfEmoji(): void
    {
        $types = $this->normalizer->rankingTypes();
        $emoji = array_column($types, 'emoji', 'key');
        $field = array_column($types, 'field', 'key');

        $this->assertSame('🟡', $emoji['points']);
        $this->assertSame('⚽', $emoji['score']);
        $this->assertSame('🔮', $emoji['winners']);
        $this->assertSame('🔴', $emoji['lantern']);
        $this->assertSame('🤔', $emoji['inconsistent']);
        // Het veld verwijst naar de bijbehorende kolom in de stand.
        $this->assertSame('weightedTotal', $field['points']);
        $this->assertSame('lanternPoints', $field['lantern']);
    }

    public function testStandingsMapEntryIncludingMovement(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getDisplayName')->willReturn('Anne');
        $user->method('getSlug')->willReturn('anne');

        $entry = new LeaderboardEntry($user);
        $entry->rank = 2;
        $entry->rankChange = ['points' => 1];
        $entry->weightedTotal = 12.5;
        $entry->lanternPoints = 3;

        $rows = $this->normalizer->standings([$entry]);

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['rank']);
        $this->assertSame('Anne', $rows[0]['player']);
        $this->assertSame(1, $rows[0]['movement'], 'movement komt uit rankChange[points].');
        $this->assertSame(12.5, $rows[0]['weightedTotal']);
        $this->assertSame(3, $rows[0]['lanternPoints']);
    }

    public function testStandingsMovementNullWhenNoChange(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getDisplayName')->willReturn('Bram');
        $user->method('getSlug')->willReturn('bram');

        $entry = new LeaderboardEntry($user);

        $this->assertNull($this->normalizer->standings([$entry])[0]['movement']);
    }
}
