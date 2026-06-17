<?php

namespace App\Api;

/**
 * Soort fout in een API-operatie — transport-agnostisch. De REST-laag vertaalt
 * dit naar een HTTP-status; de MCP-laag naar een tool-fout.
 */
enum ApiError
{
    case Unauthorized;
    case Forbidden;
    case NotFound;
    case Conflict;
    case Validation;
}
