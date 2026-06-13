<?php

namespace App\Util;

final class Slug
{
    /**
     * Maakt een URL-veilige slug van een tekst (bijv. "José Núñez" -> "jose-nunez").
     */
    public static function make(string $text): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $ascii = $ascii !== false ? $ascii : $text;

        $slug = strtolower($ascii);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'speler';
    }
}
