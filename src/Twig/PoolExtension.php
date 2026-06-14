<?php

namespace App\Twig;

use App\Pool\PoolContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig-functie active_pool(): de poule waarvan het klassement nu getoond wordt.
 */
class PoolExtension extends AbstractExtension
{
    public function __construct(private PoolContext $poolContext)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('active_pool', $this->poolContext->getActivePool(...)),
        ];
    }
}
