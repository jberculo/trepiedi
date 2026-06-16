<?php

namespace App\Controller;

use App\Api\ApiException;
use App\Api\ApiKeyResolver;
use App\Api\Operations;
use App\Flag\FlagProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * REST-API. Dezelfde logica (App\Api\Operations) voedt ook de MCP-server.
 *   - Lezen (geen sleutel): stand, wedstrijden, ronden.
 *   - Header X-API-Key: je eigen voorspelling en /api/me; beheer met een beheerderssleutel.
 */
class ApiController extends AbstractController
{
    public function __construct(
        private Operations $ops,
        private ApiKeyResolver $keys,
    ) {
    }

    #[Route('/api/standings', name: 'api_standings_default', methods: ['GET'])]
    #[Route('/api/standings/{code}', name: 'api_standings', methods: ['GET'])]
    public function standings(?string $code, Request $request): JsonResponse
    {
        $code ??= $request->query->get('pool');

        return $this->run(fn (): array => $this->ops->standings($code));
    }

    #[Route('/api/matches', name: 'api_matches', methods: ['GET'])]
    public function matches(): JsonResponse
    {
        return $this->run(fn (): array => $this->ops->matchesList());
    }

    #[Route('/api/matches/{id}', name: 'api_match', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function match(int $id): JsonResponse
    {
        return $this->run(fn (): array => $this->ops->matchDetail($id));
    }

    #[Route('/api/rounds', name: 'api_rounds', methods: ['GET'])]
    public function rounds(): JsonResponse
    {
        return $this->run(fn (): array => $this->ops->roundsList());
    }

    #[Route('/api/flags/{code}', name: 'api_flag', methods: ['GET'], requirements: ['code' => '[a-z]{2}(-[a-z]+)?'])]
    public function flag(string $code, FlagProvider $flags): Response
    {
        $svg = $flags->svg($code);
        if ($svg === null) {
            return $this->json(['error' => 'Onbekende vlag.'], Response::HTTP_NOT_FOUND);
        }

        return new Response($svg, Response::HTTP_OK, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
    {
        return $this->run(fn (): array => $this->ops->me($this->keys->fromRequest($request)));
    }

    #[Route('/api/matches/{id}/prediction', name: 'api_match_prediction', methods: ['POST', 'PUT'], requirements: ['id' => '\d+'])]
    public function prediction(int $id, Request $request): JsonResponse
    {
        return $this->run(fn (): array => $this->ops->submitPrediction($this->keys->fromRequest($request), $id, $this->body($request)));
    }

    #[Route('/api/matches/{id}/result', name: 'api_match_result', methods: ['POST', 'PUT'], requirements: ['id' => '\d+'])]
    public function result(int $id, Request $request): JsonResponse
    {
        return $this->run(fn (): array => $this->ops->setResult($this->keys->fromRequest($request), $id, $this->body($request)));
    }

    #[Route('/api/matches/{id}', name: 'api_match_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function updateMatch(int $id, Request $request): JsonResponse
    {
        return $this->run(fn (): array => $this->ops->updateMatch($this->keys->fromRequest($request), $id, $this->body($request)));
    }

    #[Route('/api/pools', name: 'api_pools', methods: ['GET'])]
    public function pools(Request $request): JsonResponse
    {
        return $this->run(fn (): array => $this->ops->poolsList($this->keys->fromRequest($request)));
    }

    #[Route('/api/pools', name: 'api_pool_create', methods: ['POST'])]
    public function createPool(Request $request): JsonResponse
    {
        return $this->run(fn (): array => $this->ops->createPool($this->keys->fromRequest($request), $this->body($request)), Response::HTTP_CREATED);
    }

    /**
     * Voert een operatie uit en vertaalt een ApiException naar de juiste JSON + status.
     *
     * @param callable(): array $fn
     */
    private function run(callable $fn, int $okStatus = Response::HTTP_OK): JsonResponse
    {
        try {
            return $this->json($fn(), $okStatus);
        } catch (ApiException $e) {
            return $this->json(['error' => $e->getMessage()], $e->status);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Request $request): array
    {
        $decoded = json_decode($request->getContent() ?: '{}', true);
        if (!is_array($decoded)) {
            throw new ApiException(Response::HTTP_BAD_REQUEST, 'Ongeldige JSON-body.');
        }

        return $decoded;
    }
}
