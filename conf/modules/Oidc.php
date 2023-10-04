<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Application\Login\Oidc\Module;
use Config\ModuleLoader;
use Psr\Container\ContainerInterface;

/**
 * OpenID Connect module configuration.
 */
class Oidc extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $this->loadModule($di, Module::class, "/login/oidc");
    }
}
