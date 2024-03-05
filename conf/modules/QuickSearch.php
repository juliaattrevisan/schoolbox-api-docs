<?php

/**
 * Configuration for the quicksearch module.
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 */

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox\QuickSearch\Module as QuickSearchModule;

class QuickSearch implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $qsModule = new QuickSearchModule($di);
        $qsModule->registerModule('/quicksearch');
    }
}
