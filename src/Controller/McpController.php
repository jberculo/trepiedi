<?php

namespace App\Controller;

use App\Api\ApiException;
use App\Api\ApiKeyResolver;
use App\Api\Operations;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * MCP-server (Streamable HTTP) op /mcp: ontsluit de Trepiedi-API als tools voor een
 * AI-assistent. JSON-RPC over één endpoint; auth via de header X-API-Key (lezen kan
 * zonder, schrijven met je sleutel). Dezelfde logica als de REST-API (App\Api\Operations).
 */
class McpController extends AbstractController
{
    private const PROTOCOL_VERSION = '2025-06-18';
    private const SERVER = ['name' => 'trepiedi', 'version' => '1.0.0'];

    public function __construct(
        private Operations $ops,
        private ApiKeyResolver $keys,
    ) {
    }

    #[Route('/mcp', name: 'api_mcp', methods: ['POST'])]
    public function endpoint(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json($this->error(null, -32700, 'Parse error'), Response::HTTP_BAD_REQUEST);
        }

        $user = $this->keys->fromRequest($request);

        // Batch (lijst van berichten) of één enkel bericht.
        $isBatch = array_is_list($payload) && $payload !== [];
        $messages = $isBatch ? $payload : [$payload];

        $responses = [];
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }
            $response = $this->handle($message, $user);
            if ($response !== null) {
                $responses[] = $response;
            }
        }

        // Alleen notificaties → niets terug te sturen.
        if ($responses === []) {
            return new Response('', Response::HTTP_ACCEPTED);
        }

        return $this->json($isBatch ? $responses : $responses[0]);
    }

    /**
     * Verwerkt één JSON-RPC-bericht. Geeft null terug voor een notificatie.
     *
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>|null
     */
    private function handle(array $message, ?User $user): ?array
    {
        $id = $message['id'] ?? null;
        $method = (string) ($message['method'] ?? '');
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        // Notificaties (geen id) verwachten geen antwoord.
        if ($id === null) {
            return null;
        }

        return match ($method) {
            'initialize' => $this->result($id, [
                'protocolVersion' => is_string($params['protocolVersion'] ?? null) ? $params['protocolVersion'] : self::PROTOCOL_VERSION,
                'capabilities' => ['tools' => (object) []],
                'serverInfo' => self::SERVER,
            ]),
            'ping' => $this->result($id, (object) []),
            'tools/list' => $this->result($id, ['tools' => $this->tools()]),
            'tools/call' => $this->result($id, $this->callTool($params, $user)),
            default => $this->error($id, -32601, sprintf('Onbekende methode: %s', $method)),
        };
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function callTool(array $params, ?User $user): array
    {
        $name = (string) ($params['name'] ?? '');
        $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        try {
            $data = match ($name) {
                'get_standings' => $this->ops->standings(isset($args['poolCode']) ? (string) $args['poolCode'] : null),
                'list_matches' => $this->ops->matchesList(),
                'get_match' => $this->ops->matchDetail((int) ($args['matchId'] ?? 0)),
                'get_rounds' => $this->ops->roundsList(),
                'whoami' => $this->ops->me($user),
                'submit_prediction' => $this->ops->submitPrediction($user, (int) ($args['matchId'] ?? 0), $args),
                'set_match_result' => $this->ops->setResult($user, (int) ($args['matchId'] ?? 0), $args),
                'update_match' => $this->ops->updateMatch($user, (int) ($args['matchId'] ?? 0), $args),
                'list_pools' => $this->ops->poolsList($user),
                'create_pool' => $this->ops->createPool($user, $args),
                default => throw new ApiException(404, sprintf('Onbekende tool: %s', $name)),
            };
        } catch (ApiException $e) {
            return ['content' => [['type' => 'text', 'text' => $e->getMessage()]], 'isError' => true];
        }

        return ['content' => [['type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tools(): array
    {
        $obj = static fn (array $props, array $required = []): array => array_filter([
            'type' => 'object',
            'properties' => $props === [] ? (object) [] : $props,
            'required' => $required,
        ], static fn ($v) => $v !== []);

        $str = ['type' => 'string'];
        $int = ['type' => 'integer'];
        $side = ['type' => 'string', 'enum' => ['home', 'away']];

        return [
            ['name' => 'get_standings', 'description' => 'Stand van een poule (zonder poolCode: de standaardpoule).', 'inputSchema' => $obj(['poolCode' => $str])],
            ['name' => 'list_matches', 'description' => 'Alle wedstrijden met uitslag, open en of ze te voorspellen zijn (incl. id).', 'inputSchema' => $obj([])],
            ['name' => 'get_match', 'description' => 'Eén wedstrijd met detail (en na de aftrap de voorspellingen).', 'inputSchema' => $obj(['matchId' => $int], ['matchId'])],
            ['name' => 'get_rounds', 'description' => 'Ronden met gewicht en aantal wedstrijden.', 'inputSchema' => $obj([])],
            ['name' => 'whoami', 'description' => 'Info over de eigenaar van de API-sleutel (vereist sleutel).', 'inputSchema' => $obj([])],
            ['name' => 'submit_prediction', 'description' => 'Je eigen voorspelling indienen/aanpassen (vereist sleutel; wedstrijd moet te voorspellen zijn).', 'inputSchema' => $obj(['matchId' => $int, 'homeScore' => $int, 'awayScore' => $int, 'advancingSide' => $side], ['matchId', 'homeScore', 'awayScore', 'advancingSide'])],
            ['name' => 'set_match_result', 'description' => 'Uitslag van een open wedstrijd zetten (beheerder).', 'inputSchema' => $obj(['matchId' => $int, 'homeScore' => $int, 'awayScore' => $int, 'advancingSide' => $side, 'finished' => ['type' => 'boolean']], ['matchId', 'homeScore', 'awayScore'])],
            ['name' => 'update_match', 'description' => 'Ploegnamen/aftrap/activeren bijwerken (beheerder).', 'inputSchema' => $obj(['matchId' => $int, 'home' => $str, 'away' => $str, 'kickoff' => $str, 'active' => ['type' => 'boolean']], ['matchId'])],
            ['name' => 'list_pools', 'description' => 'Alle poules (beheerder).', 'inputSchema' => $obj([])],
            ['name' => 'create_pool', 'description' => 'Een poule aanmaken (beheerder).', 'inputSchema' => $obj(['name' => $str, 'code' => $str, 'default' => ['type' => 'boolean']], ['name'])],
        ];
    }

    private function result(mixed $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private function error(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
