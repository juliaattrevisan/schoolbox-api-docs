<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Client;

use Alaress\Codec\Json\Base64;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Authenticator;

/**
 * Module for OpenID Connect logins.
 */
class Module extends BaseModule
{
    protected $basename = 'loginOidc';

    /**
     * @inheritdoc
     */
    protected function registerRoutes()
    {
        // Routes:
        // a GET/POST page for handling both creating an auth request, and
        // handling an auth response.
        // These routes should only exist if provider is set up and enabled.
        $this->register(self::METHOD_GET, '', 'getAuthorization');
        $this->register(self::METHOD_POST, '', 'postAuthorization');
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
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('httpClient'),
            new Base64(),
            $this->injector->get('oidc.cache'),
            new Authenticator(),
            $this->injector->get('user.jwtParser'),
            $this->injector->get('user.jwtParser.logger'),
        );
    }
}
