<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Help\SingleSignOn;

use Alaress\Web\Controller\Base;
use Schoolbox\API\Token\Jwt;
use Zend\Uri\Uri;

/**
 * Controller for SSO endpoints
 */
class Controller extends Base
{
    /**
     * Token builder: used to construct and parse tokens.
     */
    private Jwt $tokenBuilder;

    /**
     * Constructor
     *
     * @param Jwt $tokenBuilder
     */
    public function __construct(Jwt $tokenBuilder)
    {
        $this->tokenBuilder = $tokenBuilder;
    }

    /**
     * Generate a JWT, and send it to Schoolbox Help for single sign-on.
     *
     * This will redirect to `receiveTokenFromInstance` on the Help instance,
     * with the JWT in the "jwt" query string parameter.
     *
     * @api GET /help/sso/send
     * @since 21.0
     */
    public function sendTokenToHelp(): void
    {
        // Generate a link to redir to
        /** @noinspection PhpUnhandledExceptionInspection */
        $url = (new Uri($this->getConfigLoader()->get('schoolbox_help_url')))
            ->setPath($this->router->generate('helpSso.receiveTokenFromInstance'));

        // Generate a JWT
        // Need to inject more data (i.e. expiry)
        $url->setQuery(['jwt' => $this->tokenBuilder->generate($this->getUser())]);

        var_dump($url);
    }

    /**
     * Receive a JWT (in the "jwt" query string parameter), and use it to
     * identify and authenticate a user.
     *
     * @api GET /help/sso/receive
     * @since 21.0
     */
    public function receiveTokenFromInstance(): void
    {
        // Decode the JWT _without_ verifying it,
        // in order to determine the instance whence it came

        // Now we know the instance:
        // Look it up in the registry table, and get the public key stored there
        // so that the JWT can be verified

        // Now the JWT is decoded and verified:
        // create a session for the user matching the email address in the JWT
    }
}
