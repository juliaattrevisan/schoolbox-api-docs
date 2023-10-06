<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Schoolbox\Application\Login\Oidc\Module;
use Config\ModuleLoader;
use Psr\Container\ContainerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

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
        /** @var IConfigLoader $config */
        $config = $di->get('config');
        if (
            $config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER)
            && $config->get(ConfigMapper::NAME_OAUTH_ENABLE_SSO)
        ) {
            $this->loadModule($di, Module::class, "/login/oidc");
        }
    }
}
