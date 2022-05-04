<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Psr\Container\ContainerInterface;
use Schoolbox\Menu\Side\Module as MenuSideModule;
use Schoolbox\Menu\Top\Module as MenuTopModule;

class Menu extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $this->loadModule($di, MenuTopModule::class, null);
        $this->loadModule($di, MenuSideModule::class, null);
    }
}
