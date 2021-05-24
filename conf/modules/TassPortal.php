<?php

/**
 * Configuration for TASS portal module
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use External\Tass\Portal;

class TassPortal implements ModuleConfigInterface
{

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        if ($kernel->getDependencyInjector()->get('config')->get('external_type') == 'tass') {
            $module = new Portal\Module($kernel->getDependencyInjector());
            $module->init('/modules/tassportal');
        }
    }
}
