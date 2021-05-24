<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Schoolbox\Twitter\Module;

/**
 * Twitter module configuration
 */
class Twitter implements ModuleConfigInterface
{
    /**
     * @inheritDoc
     */
    public function configure($kernel)
    {
        $module = new Module($kernel->getDependencyInjector());
        $module->init('/twitter');
    }
}
