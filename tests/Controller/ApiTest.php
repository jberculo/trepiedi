<?php

namespace App\Tests\Controller;

use App\Entity\FootballMatch;
use App\Tests\FixturesWebTestCase;

/**
 * Publieke stand-/wedstrijden-API (lezen zonder sleutel) en de uitslagen-API
 * (schrijven met een beheerders-API-sleutel).
 */
class ApiTest extends FixturesWebTestCase
{
    public function testStandingsArePublicAndScopedToDefaultPool(): void
    {
        $this->client->request('GET', '/api/standings');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->assertSame('algemeen', $data['pool']['code']);
        // Per klassement een eigen, gesorteerde lijst.
        $this->assertSame(['points', 'score', 'winners', 'lantern', 'inconsistent'], array_keys($data['rankings']));
        $points = $data['rankings']['points'];
        $this->assertSame('weightedTotal', $points['field']);
        $players = array_column($points['entries'], 'player');
        $this->assertContains('Anne', $players);
        $this->assertContains('Bram', $players);
        $this->assertArrayHasKey('rank', $points['entries'][0]);
        $this->assertArrayHasKey('value', $points['entries'][0]);
        $this->assertArrayHasKey('movement', $points['entries'][0], 'Elke ranglijst toont de positieverandering per klassement.');
        // Klassement-types met emoji.
        $emoji = array_column($data['types'], 'emoji', 'key');
        $this->assertSame('🟡', $emoji['points']);
        $this->assertSame('🔴', $emoji['lantern']);
    }

    public function testStandingsExposeEachRankingSortedIncludingPenaltyRankings(): void
    {
        $this->client->request('GET', '/api/standings');

        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        // De ronde lantaarn komt ook door de API, met de meeste strafpunten eerst.
        $lantern = $data['rankings']['lantern'];
        $this->assertSame('lanternPoints', $lantern['field']);
        $this->assertSame(['Chris', 'Diana', 'Bram', 'Anne'], array_column($lantern['entries'], 'player'));
        $this->assertSame([12, 8, 5, 0], array_column($lantern['entries'], 'value'));
        $this->assertSame([1, 2, 3, 4], array_column($lantern['entries'], 'rank'));
        $this->assertArrayHasKey('movement', $lantern['entries'][0]);
    }

    public function testTimelineExposesScoredPredictionsPerMatch(): void
    {
        $this->client->request('GET', '/api/timeline');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->assertSame('algemeen', $data['pool']['code']);
        $this->assertNotEmpty($data['matches']);

        foreach ($data['matches'] as $match) {
            $this->assertNotNull($match['homeScore'], 'Alleen afgeronde wedstrijden staan in de timeline.');
        }

        $match = $data['matches'][0];
        foreach (['matchId', 'round', 'weight', 'home', 'away', 'homeScore', 'awayScore', 'predictions'] as $key) {
            $this->assertArrayHasKey($key, $match);
        }

        $byPlayer = array_column($match['predictions'], null, 'player');
        $this->assertArrayHasKey('Anne', $byPlayer, 'Iedere deelnemer staat per wedstrijd in de timeline.');

        // Anne voorspelt in de fixtures elke afgeronde wedstrijd perfect: exacte uitslag + winnaar = 6 punten.
        $anne = $byPlayer['Anne'];
        $this->assertSame($match['homeScore'], $anne['homeScore'], 'De voorspelde uitslag wordt meegegeven.');
        $this->assertSame($match['awayScore'], $anne['awayScore']);
        $this->assertTrue($anne['winner']);
        $this->assertSame(3, $anne['scorePoints']);
        $this->assertSame(6.0, (float) $anne['points']);
    }

    public function testTimelineUnknownPoolIs404(): void
    {
        $this->client->request('GET', '/api/timeline/bestaat-niet');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testSetResultViaApiMarksResultAsApiSourced(): void
    {
        $adminKey = $this->issueApiToken($this->user('admin@trepiedi.test'));
        $id = $this->openMatch()->getId();

        $this->post($id, $adminKey, ['homeScore' => 2, 'awayScore' => 1, 'advancingSide' => 'home']);
        $this->assertResponseIsSuccessful();

        $this->em->clear();
        $this->assertTrue(
            $this->em->getRepository(FootballMatch::class)->find($id)->isResultViaExternalApi(),
            'Een via de API gezette uitslag krijgt de API/MCP-vlag.'
        );

        // De vlag is ook zichtbaar in de wedstrijd-API.
        $this->client->request('GET', '/api/matches/' . $id);
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['resultViaExternalApi']);
    }

    public function testStandingsForSpecificPoolAreScoped(): void
    {
        $this->client->request('GET', '/api/standings/kantoor');

        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $players = array_column($data['rankings']['points']['entries'], 'player');

        $this->assertContains('Anne', $players);
        $this->assertContains('Chris', $players);
        $this->assertNotContains('Bram', $players, 'Kantoor bevat Bram niet.');
    }

    public function testStandingsViaQueryParameter(): void
    {
        $this->client->request('GET', '/api/standings?pool=kantoor');

        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame('kantoor', $data['pool']['code']);
        $players = array_column($data['rankings']['points']['entries'], 'player');
        $this->assertContains('Chris', $players);
        $this->assertNotContains('Bram', $players);
    }

    public function testStandingsUnknownPoolIs404(): void
    {
        $this->client->request('GET', '/api/standings/bestaat-niet');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testMatchesListIsPublicWithOpenFlag(): void
    {
        $this->client->request('GET', '/api/matches');

        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertNotEmpty($data['matches']);
        $this->assertArrayHasKey('open', $data['matches'][0]);
        $this->assertArrayHasKey('id', $data['matches'][0]);
        // Vlag-codes per ploeg.
        $this->assertArrayHasKey('homeFlag', $data['matches'][0]);
        $this->assertContains('nl', array_column($data['matches'], 'homeFlag'), 'Nederland levert vlag-code nl.');
        // De SVG's worden meegegeven in een (gededupliceerde) flags-map.
        $this->assertArrayHasKey('nl', $data['flags']);
        $this->assertStringContainsString('<svg', $data['flags']['nl']);
    }

    public function testFlagSvgEndpoint(): void
    {
        $this->client->request('GET', '/api/flags/nl');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/flags/zz');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testSetResultWithAdminKeyOnOpenMatch(): void
    {
        $adminKey = $this->issueApiToken($this->user('admin@trepiedi.test'));

        $open = $this->openMatch();
        $id = $open->getId();

        $this->post($id, $adminKey, ['homeScore' => 3, 'awayScore' => 1, 'advancingSide' => 'home', 'finished' => true]);
        $this->assertResponseIsSuccessful();

        $this->em->clear();
        $match = $this->em->getRepository(FootballMatch::class)->find($id);
        $this->assertSame(3, $match->getHomeScore());
        $this->assertSame(1, $match->getAwayScore());
        $this->assertSame('home', $match->getAdvancingSide());
        $this->assertTrue($match->isFinished());
    }

    public function testSetResultRejectedWithoutKey(): void
    {
        $id = $this->openMatch()->getId();
        $this->client->request('POST', '/api/matches/' . $id . '/result', [], [], ['CONTENT_TYPE' => 'application/json'], '{"homeScore":1,"awayScore":0,"advancingSide":"home"}');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testSetResultRejectedWithInvalidKey(): void
    {
        $id = $this->openMatch()->getId();
        $this->post($id, 'niet-bestaande-sleutel', ['homeScore' => 1, 'awayScore' => 0, 'advancingSide' => 'home']);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testSetResultRejectedForNonAdminKey(): void
    {
        $anneKey = $this->issueApiToken($this->user('anne@trepiedi.test'));

        $id = $this->openMatch()->getId();
        $this->post($id, $anneKey, ['homeScore' => 1, 'awayScore' => 0, 'advancingSide' => 'home']);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testSetResultRejectedForFinishedMatch(): void
    {
        $adminKey = $this->issueApiToken($this->user('admin@trepiedi.test'));

        $finished = $this->em->getRepository(FootballMatch::class)->findOneBy(['finished' => true]);
        $this->post($finished->getId(), $adminKey, ['homeScore' => 0, 'awayScore' => 0, 'advancingSide' => 'home']);
        $this->assertResponseStatusCodeSame(409);
    }

    public function testMatchDetailAndPredictableField(): void
    {
        $this->client->request('GET', '/api/matches');
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('predictable', $data['matches'][0]);

        $id = $this->openMatch()->getId();
        $this->client->request('GET', '/api/matches/' . $id);
        $this->assertResponseIsSuccessful();
        $detail = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame($id, $detail['id']);
        $this->assertTrue($detail['predictable'], 'Open wedstrijd is te voorspellen.');
        $this->assertArrayHasKey('predictionCount', $detail);
    }

    public function testRoundsEndpoint(): void
    {
        $this->client->request('GET', '/api/rounds');
        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertNotEmpty($data['rounds']);
        $this->assertArrayHasKey('weight', $data['rounds'][0]);
        $this->assertArrayHasKey('matchCount', $data['rounds'][0]);
    }

    public function testMeRequiresKeyAndReturnsProfile(): void
    {
        $this->client->request('GET', '/api/me');
        $this->assertResponseStatusCodeSame(401);

        $anneKey = $this->issueApiToken($this->user('anne@trepiedi.test'));

        $this->req('GET', '/api/me', $anneKey);
        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame('Anne', $data['displayName']);
        $this->assertFalse($data['admin']);
        $codes = array_column($data['pools'], 'code');
        $this->assertContains('algemeen', $codes);
        $this->assertContains('kantoor', $codes);
    }

    public function testSubmitOwnPredictionWithKey(): void
    {
        $anneKey = $this->issueApiToken($this->user('anne@trepiedi.test'));
        $open = $this->openMatch();
        $id = $open->getId();

        $this->req('POST', '/api/matches/' . $id . '/prediction', $anneKey, ['homeScore' => 2, 'awayScore' => 0, 'advancingSide' => 'home']);
        $this->assertResponseIsSuccessful();

        $this->em->clear();
        $anne = $this->user('anne@trepiedi.test');
        $match = $this->em->getRepository(FootballMatch::class)->find($id);
        $prediction = $this->em->getRepository(\App\Entity\Prediction::class)->findOneForUserAndMatch($anne, $match);
        $this->assertNotNull($prediction);
        $this->assertSame(2, $prediction->getHomeScore());
        $this->assertSame('home', $prediction->getAdvancingSide());
    }

    public function testSubmitPredictionWithoutKeyIs401(): void
    {
        $id = $this->openMatch()->getId();
        $this->req('POST', '/api/matches/' . $id . '/prediction', null, ['homeScore' => 1, 'awayScore' => 0, 'advancingSide' => 'home']);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testSubmitPredictionOnLockedMatchIs409(): void
    {
        $anneKey = $this->issueApiToken($this->user('anne@trepiedi.test'));
        $locked = $this->lockedMatch();

        $this->req('POST', '/api/matches/' . $locked->getId() . '/prediction', $anneKey, ['homeScore' => 1, 'awayScore' => 0, 'advancingSide' => 'home']);
        $this->assertResponseStatusCodeSame(409);
    }

    public function testUpdateMatchTeamsWithAdminKey(): void
    {
        $adminKey = $this->issueApiToken($this->user('admin@trepiedi.test'));
        $id = $this->openMatch()->getId();

        $this->req('PATCH', '/api/matches/' . $id, $adminKey, ['home' => 'Nederland', 'away' => 'Brazilië', 'active' => true]);
        $this->assertResponseIsSuccessful();

        $this->em->clear();
        $match = $this->em->getRepository(FootballMatch::class)->find($id);
        $this->assertSame('Nederland', $match->getHomeTeam());
        $this->assertSame('Brazilië', $match->getAwayTeam());
    }

    public function testUpdateMatchRejectedForNonAdmin(): void
    {
        $anneKey = $this->issueApiToken($this->user('anne@trepiedi.test'));
        $id = $this->openMatch()->getId();

        $this->req('PATCH', '/api/matches/' . $id, $anneKey, ['home' => 'X']);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreatePoolWithAdminKey(): void
    {
        $adminKey = $this->issueApiToken($this->user('admin@trepiedi.test'));

        $this->req('POST', '/api/pools', $adminKey, ['name' => 'Vrienden']);
        $this->assertResponseStatusCodeSame(201);

        $this->em->clear();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame('Vrienden', $data['name']);
        $this->assertNotNull($this->em->getRepository(\App\Entity\Pool::class)->findOneBy(['code' => $data['code']]));
    }

    public function testCreatePoolRejectedForNonAdmin(): void
    {
        $anneKey = $this->issueApiToken($this->user('anne@trepiedi.test'));

        $this->req('POST', '/api/pools', $anneKey, ['name' => 'Stiekem']);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testSetResultOnUnknownMatchIs404(): void
    {
        $adminKey = $this->issueApiToken($this->user('admin@trepiedi.test'));
        $this->post(999999, $adminKey, ['homeScore' => 1, 'awayScore' => 0, 'advancingSide' => 'home']);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testSetResultMissingScoreIs422(): void
    {
        $adminKey = $this->issueApiToken($this->user('admin@trepiedi.test'));
        $id = $this->openMatch()->getId();
        // awayScore ontbreekt.
        $this->post($id, $adminKey, ['homeScore' => 1, 'advancingSide' => 'home']);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testSetResultNonNumericScoreIs422(): void
    {
        $adminKey = $this->issueApiToken($this->user('admin@trepiedi.test'));
        $id = $this->openMatch()->getId();
        $this->post($id, $adminKey, ['homeScore' => 'twee', 'awayScore' => 0, 'advancingSide' => 'home']);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testSetResultInvalidSideIs422(): void
    {
        $adminKey = $this->issueApiToken($this->user('admin@trepiedi.test'));
        $id = $this->openMatch()->getId();
        $this->post($id, $adminKey, ['homeScore' => 1, 'awayScore' => 0, 'advancingSide' => 'midden']);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testSetResultFinishedWithoutSideIs422(): void
    {
        $adminKey = $this->issueApiToken($this->user('admin@trepiedi.test'));
        $id = $this->openMatch()->getId();
        // finished=true vereist advancingSide.
        $this->post($id, $adminKey, ['homeScore' => 1, 'awayScore' => 0, 'finished' => true]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testSubmitPredictionMissingScoreIs422(): void
    {
        $anneKey = $this->issueApiToken($this->user('anne@trepiedi.test'));
        $id = $this->openMatch()->getId();
        $this->req('POST', '/api/matches/' . $id . '/prediction', $anneKey, ['homeScore' => 2, 'advancingSide' => 'home']);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testSubmitPredictionInvalidSideIs422(): void
    {
        $anneKey = $this->issueApiToken($this->user('anne@trepiedi.test'));
        $id = $this->openMatch()->getId();
        $this->req('POST', '/api/matches/' . $id . '/prediction', $anneKey, ['homeScore' => 2, 'awayScore' => 0, 'advancingSide' => 'x']);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testSubmitPredictionOnInactiveMatchIs409(): void
    {
        $anneKey = $this->issueApiToken($this->user('anne@trepiedi.test'));
        $open = $this->openMatch();
        $open->setActive(false);
        $this->em->flush();

        $this->req('POST', '/api/matches/' . $open->getId() . '/prediction', $anneKey, ['homeScore' => 1, 'awayScore' => 0, 'advancingSide' => 'home']);
        $this->assertResponseStatusCodeSame(409);
    }

    public function testUpdateMatchActiveToggleLeavesTeamsUntouched(): void
    {
        $adminKey = $this->issueApiToken($this->user('admin@trepiedi.test'));
        $open = $this->openMatch();
        $id = $open->getId();
        $home = $open->getHomeTeam();

        $this->req('PATCH', '/api/matches/' . $id, $adminKey, ['active' => false]);
        $this->assertResponseIsSuccessful();

        $this->em->clear();
        $match = $this->em->getRepository(FootballMatch::class)->find($id);
        $this->assertFalse($match->isActive());
        $this->assertSame($home, $match->getHomeTeam(), 'Een active-toggle mag de ploegnaam niet wijzigen.');
    }

    public function testUpdateMatchInvalidKickoffIs422(): void
    {
        $adminKey = $this->issueApiToken($this->user('admin@trepiedi.test'));
        $id = $this->openMatch()->getId();
        $this->req('PATCH', '/api/matches/' . $id, $adminKey, ['kickoff' => 'geen-datum']);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreatePoolEmptyNameIs422(): void
    {
        $adminKey = $this->issueApiToken($this->user('admin@trepiedi.test'));
        $this->req('POST', '/api/pools', $adminKey, ['name' => '   ']);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreatePoolDuplicateCodeIs409(): void
    {
        $adminKey = $this->issueApiToken($this->user('admin@trepiedi.test'));
        // 'kantoor' bestaat al in de fixtures.
        $this->req('POST', '/api/pools', $adminKey, ['name' => 'Kopie', 'code' => 'kantoor']);
        $this->assertResponseStatusCodeSame(409);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(int $id, string $key, array $body): void
    {
        $this->req('POST', '/api/matches/' . $id . '/result', $key, $body);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function req(string $method, string $path, ?string $key, array $body = []): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($key !== null) {
            $server['HTTP_X_API_KEY'] = $key;
        }

        $this->client->request($method, $path, [], [], $server, (string) json_encode($body));
    }
}
