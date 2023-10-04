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
        // * a GET page for initialising an OIDC auth flow
        $this->register(self::METHOD_GET, '', 'getSendAuthorizationRequest');

        // * a POST page which the OIDC provider posts back to
        $this->register(self::METHOD_POST, '', 'postReceiveAuthorizationResponse');

        // These routes exist only if oAuth provider is set up
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller($this->injector->get('httpClient'));
    }
}
