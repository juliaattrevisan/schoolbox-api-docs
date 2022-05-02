<?php

/**
 * Configuration for TASS portal module
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use External\Tass\Portal;
use Psr\Container\ContainerInterface;
use Schoolbox\External\Importer\Endpoint\Factory;

class TassPortal implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $config = $di->get('config');

        if ($config->get('external_type') == 'tass' || $config->get('schooldata_import_data_source') == Factory::TASS_API) {
            $module = new Portal\Module($di);
            $module->registerModule('/modules/tassportal');
//            $module->init('/modules/tassportal');
        }
    }
}
