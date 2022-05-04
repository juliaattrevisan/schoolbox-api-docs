<?php

/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Psr\Container\ContainerInterface;
use Schoolbox;

class Application extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $this->loadModule($di, Schoolbox\Application\Login\Module::class, '/login');
        $this->loadModule($di, Schoolbox\Application\Feedback\Module::class, '/feedback');
        $this->loadModule($di, Schoolbox\Application\Policy\Module::class, '/policy');
    }
}
