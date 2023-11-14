<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use External\Isams\Register\Module;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\Importer\Endpoint\Factory;

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
            $config->get(ConfigMapper::NAME_EXTERNAL_TYPE) == 'isams'
            || $config->get(ConfigMapper::NAME_SCHOOLDATA_IMPORT_DATA_SOURCE) == Factory::ISAMS
        ) {
            $module = new Module($di);
            $module->registerModule('/modules/isams/register');
        }
    }
}
