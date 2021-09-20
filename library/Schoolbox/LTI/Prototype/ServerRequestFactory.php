<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Prototype;

use Aura\Web\Request;
use Http\Factory\Guzzle\ServerRequestFactory as GuzzleServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\Url\Factory;

/**
 * Factory for building PSR-7 server request objects out of Aura web requests.
 */
class ServerRequestFactory
{
    /**
     * Build the server request.
     *
     * @return ServerRequestInterface
     */
    public function build(Request $request): ServerRequestInterface
    {
        $serverRequestFactory = new GuzzleServerRequestFactory();
        return $serverRequestFactory->createServerRequest(
            '',
            $request->url->get(),
            $request->server->getArrayCopy(),
        );
    }
}
