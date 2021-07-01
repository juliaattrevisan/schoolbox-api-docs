<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Schoolbox\Search\Mock;
use Schoolbox\Search\User;

/**
 * Module configuration for search.
 */
class Search implements ModuleConfigInterface
{
    /**
     * @inheritDoc
     */
    public function configure($kernel): void
    {
        $module = new Mock\Module($kernel->getDependencyInjector());
        $module->init('/searchv2');

        $module = new User\Module($kernel->getDependencyInjector());
        $module->init('/search/user');
    }
}
