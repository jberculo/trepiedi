<?php

namespace App\Api;

/**
 * Fout in een API-operatie, met een soort (ApiError) maar zonder transport-detail.
 * De REST- en MCP-laag bepalen zelf wat ze ermee doen (HTTP-status / tool-fout).
 */
class ApiException extends \RuntimeException
{
    public function __construct(public readonly ApiError $error, string $message)
    {
        parent::__construct($message);
    }
}
