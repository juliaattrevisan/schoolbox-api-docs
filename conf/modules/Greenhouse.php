<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox\Greenhouse\Module as GreenhouseModule;

class Greenhouse implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $namespace = 'greenhouse';

        $greenhouseModule = new GreenhouseModule($di);
        $greenhouseModule->registerModule("/$namespace");
    }
}
