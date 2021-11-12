<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Util;

use Aura\Web\Request;
use GuzzleHttp\Psr7\Utils;
use Http\Factory\Guzzle\ServerRequestFactory as GuzzleServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Factory for building PSR-7 server request objects out of Aura web requests.
 */
class ServerRequestFactory
{
    /**
     * Build the server request.
     *
     * Currently, this includes the following from the Aura request:
     * * Method
     * * URL
     * * server vars ($_SERVER)
     * * query params ($_GET)
     * * cookies
     * * headers
     * * body ($_POST)
     * * raw body
     *
     * TODO add files from Aura request to PSR-7 request
     *
     * @param Request $request
     * @return ServerRequestInterface
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function build(Request $request): ServerRequestInterface
    {
        // Build the basic request, with query and cookie params
        /** @noinspection PhpUnhandledExceptionInspection */
        $psr7Request = (new GuzzleServerRequestFactory())
            ->createServerRequest(
                $request->method->get(),
                $request->url->get(),
                $request->server->getArrayCopy(),
            )
            ->withQueryParams($request->query->getArrayCopy())
            ->withCookieParams($request->cookies->getArrayCopy());

        // Add headers
        foreach ($request->headers->get() as $name => $value) {
            $psr7Request = $psr7Request->withHeader($name, $value);
        }

        // Add body and return
        return $psr7Request
            ->withBody(Utils::streamFor($request->content->getRaw()))
            ->withParsedBody($request->post->getArrayCopy());
    }
}
