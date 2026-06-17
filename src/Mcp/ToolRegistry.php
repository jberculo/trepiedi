<?php

namespace App\Mcp;

use App\Api\ApiError;
use App\Api\ApiException;
use App\Api\ReadApi;
use App\Api\WriteApi;
use App\Entity\User;

/**
 * De MCP-tools: één bron voor zowel de tools/list (definities + JSON Schema) als
 * de uitvoering (tools/call). De handlers delegeren naar dezelfde API-services als
 * de REST-laag. Gooit ApiException; de transportlaag bepaalt wat ze ermee doet.
 */
class ToolRegistry
{
    public function __construct(
        private ReadApi $read,
        private WriteApi $write,
    ) {
    }

    /**
     * @return list<array{name: string, description: string, inputSchema: array<string, mixed>}>
     */
    public function definitions(): array
    {
        $str = ['type' => 'string'];
        $int = ['type' => 'integer'];
        $bool = ['type' => 'boolean'];
        $side = ['type' => 'string', 'enum' => ['home', 'away']];

        return [
            $this->tool('get_standings', 'Stand van een poule (zonder poolCode: de standaardpoule).', ['poolCode' => $str]),
            $this->tool('list_matches', 'Alle wedstrijden met uitslag, open en of ze te voorspellen zijn (incl. id).', []),
            $this->tool('get_match', 'Eén wedstrijd met detail (en na de aftrap de voorspellingen).', ['matchId' => $int], ['matchId']),
            $this->tool('get_rounds', 'Ronden met gewicht en aantal wedstrijden.', []),
            $this->tool('whoami', 'Info over de eigenaar van de API-sleutel (vereist sleutel).', []),
            $this->tool('submit_prediction', 'Je eigen voorspelling indienen/aanpassen (vereist sleutel; wedstrijd moet te voorspellen zijn).', ['matchId' => $int, 'homeScore' => $int, 'awayScore' => $int, 'advancingSide' => $side], ['matchId', 'homeScore', 'awayScore', 'advancingSide']),
            $this->tool('set_match_result', 'Uitslag van een open wedstrijd zetten (beheerder).', ['matchId' => $int, 'homeScore' => $int, 'awayScore' => $int, 'advancingSide' => $side, 'finished' => $bool], ['matchId', 'homeScore', 'awayScore']),
            $this->tool('update_match', 'Ploegnamen/aftrap/activeren bijwerken (beheerder).', ['matchId' => $int, 'home' => $str, 'away' => $str, 'kickoff' => $str, 'active' => $bool], ['matchId']),
            $this->tool('list_pools', 'Alle poules (beheerder).', []),
            $this->tool('create_pool', 'Een poule aanmaken (beheerder).', ['name' => $str, 'code' => $str, 'default' => $bool], ['name']),
        ];
    }

    /**
     * Voert een tool uit. Geeft de data terug of gooit ApiException.
     *
     * @param array<string, mixed> $args
     */
    public function call(string $name, array $args, ?User $user): array
    {
        return match ($name) {
            'get_standings' => $this->read->standings(isset($args['poolCode']) ? (string) $args['poolCode'] : null),
            'list_matches' => $this->read->matchesList(),
            'get_match' => $this->read->matchDetail((int) ($args['matchId'] ?? 0)),
            'get_rounds' => $this->read->roundsList(),
            'whoami' => $this->read->me($user),
            'submit_prediction' => $this->write->submitPrediction($user, (int) ($args['matchId'] ?? 0), $args),
            'set_match_result' => $this->write->setResult($user, (int) ($args['matchId'] ?? 0), $args),
            'update_match' => $this->write->updateMatch($user, (int) ($args['matchId'] ?? 0), $args),
            'list_pools' => $this->write->poolsList($user),
            'create_pool' => $this->write->createPool($user, $args),
            default => throw new ApiException(ApiError::NotFound, sprintf('Onbekende tool: %s', $name)),
        };
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param list<string>                        $required
     *
     * @return array{name: string, description: string, inputSchema: array<string, mixed>}
     */
    private function tool(string $name, string $description, array $properties, array $required = []): array
    {
        $schema = ['type' => 'object', 'properties' => $properties === [] ? (object) [] : $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return ['name' => $name, 'description' => $description, 'inputSchema' => $schema];
    }
}
