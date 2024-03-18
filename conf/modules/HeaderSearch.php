<?php

/**
 * Configuration for the headersearch module.
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 */

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox\HeaderSearch\Module as HeaderSearchModule;

class HeaderSearch implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $qsModule = new HeaderSearchModule($di);
        $qsModule->registerModule('/headersearch');
    }
}
