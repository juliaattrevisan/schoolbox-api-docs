<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\API;

use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Authenticator;
use Schoolbox\MobileApp\RegistryMapper;
use Schoolbox\MobileApp\ServiceMapper;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    protected $basename = 'api';


    /**
     * @inheritDoc
     */
    protected function registerRoutes(): void
    {
        $this->register('POST', '/register/{provider}', 'register');
        $this->register('POST', '/session', 'initSession');
    }


    /**
     * @param ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'auth'           => $injector->lazyNew(Authenticator::class),
                    'userMapper'     => $this->getMapperFactory()->getLazy(UserMapper::class),
                    'registryMapper' => $this->getMapperFactory()->getLazy(RegistryMapper::class),
                    'serviceMapper'  => $this->getMapperFactory()->getLazy(ServiceMapper::class),
                ]
            )
        );
    }
}
