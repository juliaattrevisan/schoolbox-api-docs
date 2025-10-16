<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Config\ModuleLoader;
use Psr\Container\ContainerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\DifferentiationProfile\Module;

class DifferentiationProfiles extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        if ($di->get('config')->get(ConfigMapper::NAME_DIFFERENTIATION_PROFILE_ENABLED)) {
            $namespace = 'differentiation-profiles';

            $module = new Module($di);
            $module->registerModule("/$namespace");
        }
    }
}
