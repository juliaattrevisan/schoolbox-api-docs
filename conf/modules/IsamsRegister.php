<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use External\IConnector;
use External\Isams\Register\Module;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\Importer\Endpoint\Factory;

/**
 * Module configuration for the iSams register.
 */
class IsamsRegister implements ModuleConfigInterface
{
    /**
     * @param ContainerInterface $di
     * @return void
     * @throws ContainerExceptionInterface
     */
    public function configure(ContainerInterface $di): void
    {
        $config = $di->get('config');
        if (
            IConnector::EXTERNAL_TYPE_ISAMS === $config->get(ConfigMapper::NAME_EXTERNAL_TYPE)
            || Factory::ISAMS === $config->get(ConfigMapper::NAME_SCHOOLDATA_IMPORT_DATA_SOURCE)
        ) {
            $module = new Module($di);
            $module->registerModule('/modules/isams/register');
        }
    }
}
