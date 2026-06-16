<?php

namespace App\Flag;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Levert de SVG-inhoud van een vlag (flag-icons, lokaal gebundeld in assets/flags).
 */
class FlagProvider
{
    /** @var array<string, ?string> */
    private array $cache = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%/assets/flags')]
        private string $dir,
    ) {
    }

    /**
     * SVG-markup voor een flag-icons-code (bijv. "nl", "gb-eng"), of null.
     */
    public function svg(?string $code): ?string
    {
        if ($code === null || preg_match('/^[a-z]{2}(-[a-z]+)?$/', $code) !== 1) {
            return null; // ook meteen padtraversal-veilig
        }
        if (array_key_exists($code, $this->cache)) {
            return $this->cache[$code];
        }

        $file = $this->dir . '/' . $code . '.svg';
        $svg = is_file($file) ? file_get_contents($file) : false;

        return $this->cache[$code] = ($svg !== false ? $svg : null);
    }
}
