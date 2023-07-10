<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform;

use CCMBenchmark\Ting\ApiPlatform\DependencyInjection\FilterCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class TingApiPlatformBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new FilterCompilerPass());
    }

}
