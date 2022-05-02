<?php

/**
 * help.schoolbox.com.au
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Help;

use Config\ModuleLoader;
use Custom\Help\Hero\Module as HeroModule;
use Psr\Container\ContainerInterface;

class Hero extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $this->loadModule($di, HeroModule::class, '/hero');
    }
}
