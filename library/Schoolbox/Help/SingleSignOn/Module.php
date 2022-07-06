<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Help\SingleSignOn;

use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Authenticator;
use Schoolbox\Help\InstanceRegistry\Mapper as InstanceRegistryMapper;
use Schoolbox\UserMapper;

/**
 * Module for SSO endpoints
 */
class Module extends BaseModule
{
    protected $basename = 'helpSso';

    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        $this->register('GET', '/send', 'sendTokenToHelp', new IsLoggedIn());
        $this->register('GET', '/receive', 'receiveTokenFromInstance');
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('user.tokenBuilder'),
            $this->injector->get('user.jwtParser'),
            new Authenticator(),
        );
    }
}
