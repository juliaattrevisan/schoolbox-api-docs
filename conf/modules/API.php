<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Psr\Container\ContainerInterface;
use Schoolbox;

class API extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $this->loadModule($di, Schoolbox\API\Module::class, '/api');

        $this->loadModule($di, Schoolbox\Admin\API\Module::class, '/api/admin');

        $this->loadModule($di, Schoolbox\APIGroup\Module::class, '/api/user');

        $this->loadModule($di, Schoolbox\APIUser\Module::class, '/api/user');

        $this->loadModule($di, Schoolbox\APIUser\Notify\Module::class, '/api/user');
    }
}
