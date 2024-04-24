<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Schoolbox\Oidc\Client;
use Schoolbox\Oidc\Server;
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

        // OIDC client
        if (
            $config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER)
            && $config->get(ConfigMapper::NAME_OAUTH_ENABLE_SSO)
        ) {
            $this->loadModule($di, Client\Module::class, "/login/oidc");
        }

        // OIDC server
        $this->loadModule($di, Server\Module::class, "");
    }
}
