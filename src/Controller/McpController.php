<?php

namespace App\Controller;

use App\Api\ApiException;
use App\Api\ApiKeyResolver;
use App\Entity\User;
use App\Mcp\ToolRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * MCP-server (Streamable HTTP) op /mcp: JSON-RPC over één endpoint. Deze controller
 * doet alleen het protocol; de tools komen uit ToolRegistry en delen de logica met
 * de REST-API. Auth via de header X-API-Key.
 */
class McpController extends AbstractController
{
    private const PROTOCOL_VERSION = '2025-06-18';
    private const SERVER = ['name' => 'trepiedi', 'version' => '1.0.0'];

    public function __construct(
        private ToolRegistry $tools,
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

        $isBatch = array_is_list($payload) && $payload !== [];
        $messages = $isBatch ? $payload : [$payload];

        $responses = [];
        foreach ($messages as $message) {
            if (is_array($message) && ($response = $this->handle($message, $user)) !== null) {
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
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>|null
     */
    private function handle(array $message, ?User $user): ?array
    {
        $id = $message['id'] ?? null;
        if ($id === null) {
            return null; // notificatie
        }

        $method = (string) ($message['method'] ?? '');
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        return match ($method) {
            'initialize' => $this->result($id, [
                'protocolVersion' => is_string($params['protocolVersion'] ?? null) ? $params['protocolVersion'] : self::PROTOCOL_VERSION,
                'capabilities' => ['tools' => (object) []],
                'serverInfo' => self::SERVER,
            ]),
            'ping' => $this->result($id, (object) []),
            'tools/list' => $this->result($id, ['tools' => $this->tools->definitions()]),
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
            $data = $this->tools->call($name, $args, $user);
        } catch (ApiException $e) {
            return ['content' => [['type' => 'text', 'text' => $e->getMessage()]], 'isError' => true];
        }

        return ['content' => [['type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]]];
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
