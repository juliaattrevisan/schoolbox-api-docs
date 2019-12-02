<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Schoolbox\Search\Mock;

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
    }
}
