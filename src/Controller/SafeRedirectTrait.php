<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;

/**
 * Veilig terugkeren naar de pagina waar de gebruiker vandaan kwam (de Referer),
 * zonder open-redirect: alleen een interne URL op dezelfde host en met een pad
 * dat met "/" begint wordt geaccepteerd. Anders null (val terug op een vaste route).
 */
trait SafeRedirectTrait
{
    private function safeRedirectTarget(Request $request): ?string
    {
        $referer = $request->headers->get('referer');
        if (!is_string($referer) || $referer === '') {
            return null;
        }

        $parts = parse_url($referer);
        if ($parts === false) {
            return null;
        }

        $host = $parts['host'] ?? null;
        if ($host !== null && $host !== $request->getHost()) {
            return null;
        }

        $path = $parts['path'] ?? '';
        if (!is_string($path) || !str_starts_with($path, '/')) {
            return null;
        }

        $target = $path;
        if (isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '') {
            $target .= '?' . $parts['query'];
        }

        return $target;
    }
}
