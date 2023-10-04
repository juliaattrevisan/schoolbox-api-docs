<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Application\Login\Oidc;

use Alaress\Web\Controller\Base;

/**
 * Controller for OpenID Connect logins.
 */
class Controller extends Base
{
    /**
     * Send an OpenID Connect authorization request to this instance's oAuth
     * provider.
     *
     * @api GET /login/oidc
     * @since 23.1
     *
     * @return void
     */
    public function getSendAuthorizationRequest(): void
    {

    }

    /**
     * Receive an OpenID Connect authorization response from this instance's
     * oAuth provider, and initialize a user session if it succeeded.
     *
     * @api GET /login/oidc
     * @since 23.1
     *
     * @return void
     */
    public function postReceiveAuthorizationResponse(): void
    {

    }
}
