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
use Schoolbox\External\Importer\Endpoint\Factory;

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
        $config = $kernel->getDependencyInjector()->get('config');

        if ($config->get('external_type') == 'tass' || $config->get('schooldata_import_data_source') == Factory::TASS_API) {
            $module = new Portal\Module($kernel->getDependencyInjector());
            $module->init('/modules/tassportal');
        }
    }
}
