<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Help\SingleSignOn;

use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Schoolbox\InstanceRegistry\Mapper as InstanceRegistryMapper;
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
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'tokenBuilder'           => $injector->lazyGet('user.tokenBuilder'),
                    'userMapper'             => $this->getMapperFactory()->getLazy(UserMapper::class),
                    'instanceRegistryMapper' => $this->getMapperFactory()->getLazy(InstanceRegistryMapper::class),
                ]
            )
        );
    }
}
