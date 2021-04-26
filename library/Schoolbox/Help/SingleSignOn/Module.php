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
use Aura\Router\Router;

/**
 * Module for SSO endpoints
 */
class Module extends BaseModule
{
    protected $basename = 'helpSso';

    protected function registerRoutes()
    {
        $this->register('GET', '/send', 'sendTokenToHelp', new IsLoggedIn());
    }

    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'tokenBuilder' => $injector->lazyGet('user.tokenBuilder'),
                ]
            )
        );
    }
}
