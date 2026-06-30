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

        // invertedMovement: alleen de lantaarn keert de kleur om (stijgen = ongunstig).
        $inverted = array_column($types, 'invertedMovement', 'key');
        $this->assertTrue($inverted['lantern']);
        $this->assertFalse($inverted['inconsistent']);
        $this->assertFalse($inverted['points']);
    }

    public function testRankingsReturnsOneSortedListPerKlassement(): void
    {
        $anne = $this->entry('Anne', 12.0, 5, 3, 0, 0, ['points' => 1, 'score' => 0]);
        $bram = $this->entry('Bram', 12.0, 8, 2, 4, 1, ['points' => -1]);
        $chris = $this->entry('Chris', 3.0, 2, 0, 6, 2);

        $rankings = $this->normalizer->rankings([$anne, $bram, $chris]);

        $this->assertSame(['points', 'score', 'winners', 'lantern', 'inconsistent'], array_keys($rankings));

        // Punten: gesorteerd op weightedTotal (desc), tie-aware rang, movement per speler.
        $points = $rankings['points'];
        $this->assertSame('weightedTotal', $points['field']);
        $this->assertSame(['Anne', 'Bram', 'Chris'], array_column($points['entries'], 'player'));
        $this->assertSame([1, 1, 3], array_column($points['entries'], 'rank'), 'Gelijke waarde => gedeelde rang.');
        $this->assertSame([12.0, 12.0, 3.0], array_column($points['entries'], 'value'));
        $this->assertSame([1, -1, null], array_column($points['entries'], 'movement'));

        // Balletjestrui sorteert anders (op scorePoints): Bram bovenaan.
        $this->assertSame(['Bram', 'Anne', 'Chris'], array_column($rankings['score']['entries'], 'player'));
        $this->assertSame(0, $rankings['score']['entries'][1]['movement'], 'Annes score-movement uit rankChange[score].');
    }

    public function testRankingsLanternRanksMostPenaltiesFirst(): void
    {
        $anne = $this->entry('Anne', 12.0, 5, 3, 0, 0);
        $bram = $this->entry('Bram', 12.0, 8, 2, 4, 1);
        $chris = $this->entry('Chris', 3.0, 2, 0, 6, 2);

        $lantern = $this->normalizer->rankings([$anne, $bram, $chris])['lantern'];

        $this->assertSame('lanternPoints', $lantern['field']);
        $this->assertSame(['Chris', 'Bram', 'Anne'], array_column($lantern['entries'], 'player'));
        $this->assertSame([6, 4, 0], array_column($lantern['entries'], 'value'));
        // Geen historie meegegeven => movement overal null.
        $this->assertSame([null, null, null], array_column($lantern['entries'], 'movement'));
    }

    private function entry(string $name, float $weighted, int $score, int $advance, int $lantern, int $inconsistent, array $rankChange = []): LeaderboardEntry
    {
        $user = $this->createStub(User::class);
        $user->method('getDisplayName')->willReturn($name);
        $user->method('getSlug')->willReturn(strtolower($name));

        $entry = new LeaderboardEntry($user);
        $entry->weightedTotal = $weighted;
        $entry->scorePoints = $score;
        $entry->advanceCount = $advance;
        $entry->lanternPoints = $lantern;
        $entry->inconsistentCount = $inconsistent;
        $entry->rankChange = $rankChange;

        return $entry;
    }
}
