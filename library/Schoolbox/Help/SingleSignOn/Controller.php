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
    private Jwt $tokenBuilder;

    public function __construct(Jwt $tokenBuilder)
    {
        $this->tokenBuilder = $tokenBuilder;
    }

    public function sendTokenToHelp()
    {
        // Need to redir to the link
        $url = new Uri(
            $this->getConfigLoader()->get('schoolbox_help_url') . $this->router->generate('helpSso.receiveTokenFromInstance')
        );

        // Generate a JWT
        // Need to inject more data (i.e. expiry)
        $url->setQuery(['jwt' => $this->tokenBuilder->generate($this->getUser())]);

        var_dump($url);
    }

    public function receiveTokenFromInstance()
    {
        // Look at the JWT
    }
}
