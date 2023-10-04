<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Application\Login\Oidc;

use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;

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
        // These routes should only exist if oAuth provider is set up.
        $this->register(self::METHOD_GET, '', 'getAuthorization');
        $this->register(self::METHOD_POST, '', 'postAuthorization');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller($this->injector->get('httpClient'));
    }
}
