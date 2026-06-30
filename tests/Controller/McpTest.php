<?php

namespace App\Tests\Controller;

use App\Entity\FootballMatch;
use App\Entity\Prediction;
use App\Tests\FixturesWebTestCase;

/**
 * MCP-endpoint (/mcp): JSON-RPC over HTTP, met de Trepiedi-tools.
 */
class McpTest extends FixturesWebTestCase
{
    public function testInitialize(): void
    {
        $res = $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18']]);

        $this->assertResponseIsSuccessful();
        $this->assertSame('trepiedi', $res['result']['serverInfo']['name']);
        $this->assertArrayHasKey('protocolVersion', $res['result']);
    }

    public function testToolsListContainsTools(): void
    {
        $res = $this->rpc(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']);

        $names = array_column($res['result']['tools'], 'name');
        foreach (['get_standings', 'get_timeline', 'list_matches', 'submit_prediction', 'set_match_result', 'create_pool'] as $tool) {
            $this->assertContains($tool, $names);
        }
    }

    public function testNotificationGives202(): void
    {
        $this->rpc(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);
        $this->assertResponseStatusCodeSame(202);
    }

    public function testUnknownMethodGivesError(): void
    {
        $res = $this->rpc(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'doesnotexist']);
        $this->assertSame(-32601, $res['error']['code']);
    }

    public function testCallGetStandingsWithoutKey(): void
    {
        $res = $this->rpc(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'get_standings', 'arguments' => []]]);

        $this->assertArrayNotHasKey('isError', $res['result']);
        $payload = json_decode($res['result']['content'][0]['text'], true);
        $this->assertSame('algemeen', $payload['pool']['code']);
        // MCP deelt dezelfde ReadApi, dus get_standings geeft de stand per klassement.
        $this->assertArrayHasKey('rankings', $payload);
        $this->assertArrayHasKey('points', $payload['rankings']);
    }

    public function testCallGetTimelineWithoutKey(): void
    {
        $res = $this->rpc(['jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/call', 'params' => ['name' => 'get_timeline', 'arguments' => []]]);

        $this->assertArrayNotHasKey('isError', $res['result']);
        $payload = json_decode($res['result']['content'][0]['text'], true);
        $this->assertSame('algemeen', $payload['pool']['code']);
        $this->assertNotEmpty($payload['matches']);
        $this->assertArrayHasKey('predictions', $payload['matches'][0]);
    }

    public function testCallSubmitPredictionWithKey(): void
    {
        $anneKey = $this->issueApiToken($this->user('anne@trepiedi.test'));
        $id = $this->openMatch()->getId();

        $res = $this->rpc(
            ['jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/call', 'params' => ['name' => 'submit_prediction', 'arguments' => ['matchId' => $id, 'homeScore' => 3, 'awayScore' => 2, 'advancingSide' => 'home']]],
            $anneKey,
        );

        $this->assertArrayNotHasKey('isError', $res['result']);

        $this->em->clear();
        $anne = $this->user('anne@trepiedi.test');
        $match = $this->em->getRepository(FootballMatch::class)->find($id);
        $prediction = $this->em->getRepository(Prediction::class)->findOneForUserAndMatch($anne, $match);
        $this->assertNotNull($prediction);
        $this->assertSame(3, $prediction->getHomeScore());
    }

    public function testCallSetResultViaMcpMarksResultAsExternal(): void
    {
        $adminKey = $this->issueApiToken($this->user('admin@trepiedi.test'));
        $id = $this->openMatch()->getId();

        $res = $this->rpc(
            ['jsonrpc' => '2.0', 'id' => 8, 'method' => 'tools/call', 'params' => ['name' => 'set_match_result', 'arguments' => ['matchId' => $id, 'homeScore' => 2, 'awayScore' => 1, 'advancingSide' => 'home']]],
            $adminKey,
        );
        $this->assertArrayNotHasKey('isError', $res['result']);

        $this->em->clear();
        $match = $this->em->getRepository(FootballMatch::class)->find($id);
        $this->assertTrue($match->isResultViaExternalApi(), 'Een uitslag via de MCP-tool krijgt ook de API/MCP-vlag.');
    }

    public function testCallSubmitPredictionWithoutKeyIsError(): void
    {
        $id = $this->openMatch()->getId();
        $res = $this->rpc(['jsonrpc' => '2.0', 'id' => 6, 'method' => 'tools/call', 'params' => ['name' => 'submit_prediction', 'arguments' => ['matchId' => $id, 'homeScore' => 1, 'awayScore' => 0, 'advancingSide' => 'home']]]);

        $this->assertTrue($res['result']['isError']);
    }

    /**
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>
     */
    private function rpc(array $message, ?string $key = null): array
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($key !== null) {
            $server['HTTP_X_API_KEY'] = $key;
        }
        $this->client->request('POST', '/mcp', [], [], $server, (string) json_encode($message));

        return json_decode((string) $this->client->getResponse()->getContent(), true) ?: [];
    }
}
