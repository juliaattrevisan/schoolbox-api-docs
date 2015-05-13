<?php
/**
 * Configuration for TASS portal module
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;

class TassPortal implements ModuleConfigInterface {

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        if ($kernel->getDependencyInjector()->get('config')->get('external_type') == 'tass') {
            $module = new \External_Tass_PortalModule($kernel->getDependencyInjector());
            $module->init('/modules/tassportal');
        }
    }
}
