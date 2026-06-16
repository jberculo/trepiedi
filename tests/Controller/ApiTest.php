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

    /**
     * @param array<string, mixed> $body
     */
    private function post(int $id, string $key, array $body): void
    {
        $this->client->request(
            'POST',
            '/api/matches/' . $id . '/result',
            [],
            [],
            ['HTTP_X_API_KEY' => $key, 'CONTENT_TYPE' => 'application/json'],
            (string) json_encode($body),
        );
    }
}
