<?php

namespace App;

use App\Util\AppTime;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct(string $environment, bool $debug)
    {
        // Vastpinnen vóór er ook maar één datum wordt geparset, opgeslagen of
        // vergeleken, zodat het gedrag niet afhangt van de server-tijdzone.
        AppTime::install();

        parent::__construct($environment, $debug);
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
