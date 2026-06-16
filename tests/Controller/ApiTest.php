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
        $players = array_column($data['standings'], 'player');
        $this->assertContains('Anne', $players);
        $this->assertContains('Bram', $players);
        $this->assertArrayHasKey('rank', $data['standings'][0]);
        $this->assertArrayHasKey('weightedTotal', $data['standings'][0]);
        $this->assertArrayHasKey('movement', $data['standings'][0], 'Stand toont ook recente positieverandering.');
        // Klassement-types met emoji.
        $emoji = array_column($data['types'], 'emoji', 'key');
        $this->assertSame('🟡', $emoji['points']);
        $this->assertSame('🔴', $emoji['lantern']);
    }

    public function testStandingsForSpecificPoolAreScoped(): void
    {
        $this->client->request('GET', '/api/standings/kantoor');

        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $players = array_column($data['standings'], 'player');

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
        $players = array_column($data['standings'], 'player');
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
        $admin = $this->user('admin@trepiedi.test');
        $admin->setApiToken('admin-key-123');
        $this->em->flush();

        $open = $this->openMatch();
        $id = $open->getId();

        $this->post($id, 'admin-key-123', ['homeScore' => 3, 'awayScore' => 1, 'advancingSide' => 'home', 'finished' => true]);
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
        $anne = $this->user('anne@trepiedi.test');
        $anne->setApiToken('anne-key-123');
        $this->em->flush();

        $id = $this->openMatch()->getId();
        $this->post($id, 'anne-key-123', ['homeScore' => 1, 'awayScore' => 0, 'advancingSide' => 'home']);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testSetResultRejectedForFinishedMatch(): void
    {
        $admin = $this->user('admin@trepiedi.test');
        $admin->setApiToken('admin-key-123');
        $this->em->flush();

        $finished = $this->em->getRepository(FootballMatch::class)->findOneBy(['finished' => true]);
        $this->post($finished->getId(), 'admin-key-123', ['homeScore' => 0, 'awayScore' => 0, 'advancingSide' => 'home']);
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

        $anne = $this->user('anne@trepiedi.test');
        $anne->setApiToken('anne-key-123');
        $this->em->flush();

        $this->req('GET', '/api/me', 'anne-key-123');
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
        $anne = $this->user('anne@trepiedi.test');
        $anne->setApiToken('anne-key-123');
        $this->em->flush();
        $open = $this->openMatch();
        $id = $open->getId();

        $this->req('POST', '/api/matches/' . $id . '/prediction', 'anne-key-123', ['homeScore' => 2, 'awayScore' => 0, 'advancingSide' => 'home']);
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
        $anne = $this->user('anne@trepiedi.test');
        $anne->setApiToken('anne-key-123');
        $this->em->flush();
        $locked = $this->lockedMatch();

        $this->req('POST', '/api/matches/' . $locked->getId() . '/prediction', 'anne-key-123', ['homeScore' => 1, 'awayScore' => 0, 'advancingSide' => 'home']);
        $this->assertResponseStatusCodeSame(409);
    }

    public function testUpdateMatchTeamsWithAdminKey(): void
    {
        $admin = $this->user('admin@trepiedi.test');
        $admin->setApiToken('admin-key-123');
        $this->em->flush();
        $id = $this->openMatch()->getId();

        $this->req('PATCH', '/api/matches/' . $id, 'admin-key-123', ['home' => 'Nederland', 'away' => 'Brazilië', 'active' => true]);
        $this->assertResponseIsSuccessful();

        $this->em->clear();
        $match = $this->em->getRepository(FootballMatch::class)->find($id);
        $this->assertSame('Nederland', $match->getHomeTeam());
        $this->assertSame('Brazilië', $match->getAwayTeam());
    }

    public function testUpdateMatchRejectedForNonAdmin(): void
    {
        $anne = $this->user('anne@trepiedi.test');
        $anne->setApiToken('anne-key-123');
        $this->em->flush();
        $id = $this->openMatch()->getId();

        $this->req('PATCH', '/api/matches/' . $id, 'anne-key-123', ['home' => 'X']);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreatePoolWithAdminKey(): void
    {
        $admin = $this->user('admin@trepiedi.test');
        $admin->setApiToken('admin-key-123');
        $this->em->flush();

        $this->req('POST', '/api/pools', 'admin-key-123', ['name' => 'Vrienden']);
        $this->assertResponseStatusCodeSame(201);

        $this->em->clear();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame('Vrienden', $data['name']);
        $this->assertNotNull($this->em->getRepository(\App\Entity\Pool::class)->findOneBy(['code' => $data['code']]));
    }

    public function testCreatePoolRejectedForNonAdmin(): void
    {
        $anne = $this->user('anne@trepiedi.test');
        $anne->setApiToken('anne-key-123');
        $this->em->flush();

        $this->req('POST', '/api/pools', 'anne-key-123', ['name' => 'Stiekem']);
        $this->assertResponseStatusCodeSame(403);
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
