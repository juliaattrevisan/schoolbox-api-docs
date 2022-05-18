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
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\Importer\Endpoint\Factory;

class TassPortal implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $config = $di->get('config');

        if (
            $config->get(ConfigMapper::NAME_EXTERNAL_TYPE) == 'tass'
            || $config->get(ConfigMapper::NAME_SCHOOLDATA_IMPORT_DATA_SOURCE) == Factory::TASS_API
        ) {
            $module = new Portal\Module($di);
            $module->registerModule('/modules/tassportal');
        }
    }
}
