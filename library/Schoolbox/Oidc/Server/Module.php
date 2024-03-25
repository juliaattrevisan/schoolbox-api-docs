<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;

/**
 * Module for OpenID Connect identity provision.
 */
class Module extends BaseModule
{
    protected $basename = 'oidcServer';

    /**
     * Register routes for:
     *
     * * authorizing access to a relying party to a Schoolbox identity
     * * exchanging an authorization code or client/secret pair for an access
     *   token
     */
    protected function registerRoutes()
    {
        // Routes:
        // authorization page: user either allows or denies RP access to SB data
        $this->register(self::METHOD_GET, '/oidc/authorize', 'getAuthorization');
        $this->register(self::METHOD_POST, '/oidc/authorize', 'postAuthorization');

//        // Allow/deny authorization request
//
//        // access token: reply to RP with an access token
//        $this->register(self::METHOD_GET, '/oidc/token', 'getAccessTokenRequest');
//        $this->register(self::METHOD_POST, '/oidc/token', 'postAccessTokenRequest');
//
//        // well-known URLs: JWKS?
//        $this->register(self::METHOD_GET, '/.well-known/jwks.json', 'getJsonWebKeySet');
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Controller
    {
        return new Controller();
    }
}
