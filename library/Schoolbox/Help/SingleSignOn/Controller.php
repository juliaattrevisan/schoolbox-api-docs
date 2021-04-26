<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Help\SingleSignOn;

use Alaress\Web\Controller\Base;
use Schoolbox\API\Token\Jwt;

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
        // Generate a JWT
        // Need to inject more data (i.e. expiry)
        $this->tokenBuilder->generate($this->getUser());
    }
}
