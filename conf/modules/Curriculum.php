<?php

/**
 * Configuration for the curriculum mapping module.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox\Curriculum\Api\Module as ApiModule;
use Schoolbox\Curriculum\Import\Module as ImportModule;
use Schoolbox\Curriculum\Module;

class Curriculum implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $module = new Module($di);
        $module->registerModule('/curriculum');

        $importModule = new ImportModule($di);
        $importModule->registerModule('/curriculum/import');

        $apiModule = new ApiModule($di);
        $apiModule->registerModule('/api/curriculum');

        // Submodules:
        // * learning areas
        // * learning levels
        // * outcomes?
        // * strands? (optional organisation of standards?)
    }
}
