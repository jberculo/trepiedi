<?php

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Geeft de wijzigingstijd van een bestand in public/ terug, voor cache-busting
 * van CSS/afbeeldingen (zodat aanpassingen altijd doorkomen).
 */
class AssetVersionExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private string $publicDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('asset_version', $this->assetVersion(...)),
        ];
    }

    public function assetVersion(string $path): string
    {
        $file = rtrim($this->publicDir, '/') . '/' . ltrim($path, '/');

        return is_file($file) ? (string) filemtime($file) : '0';
    }
}
