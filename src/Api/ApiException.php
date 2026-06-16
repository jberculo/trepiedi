<?php

namespace App\Api;

/**
 * Fout in een API-operatie, met een bijbehorende HTTP-achtige status (401/403/
 * 404/409/422). De transportlaag (REST of MCP) vertaalt 'm naar het juiste antwoord.
 */
class ApiException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
