<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\API;

use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Authenticator;
use Schoolbox\MobileApp\RegistryMapper;
use Schoolbox\MobileApp\ServiceMapper;

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
        $this->register('GET', '/session', 'initSessionFromQueryString');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            new Authenticator(),
            $this->getMapperFactory()->get(RegistryMapper::class),
            $this->getMapperFactory()->get(ServiceMapper::class),
            $this->injector->get('user.jwtParser'),
        );
    }
}
