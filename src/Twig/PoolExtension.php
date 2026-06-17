<?php

namespace App\Twig;

use App\Pool\PoolContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig-functies:
 *   - active_pool(): de poule waarvan het klassement nu getoond wordt;
 *   - switchable_pools(): actieve poules waar de gebruiker tussen kan wisselen;
 *   - pool_orphan(): of de ingelogde speler in geen enkele actieve poule meer zit.
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
            new TwigFunction('switchable_pools', $this->poolContext->getSwitchablePools(...)),
            new TwigFunction('pool_orphan', $this->poolContext->currentUserIsOrphan(...)),
        ];
    }
}
