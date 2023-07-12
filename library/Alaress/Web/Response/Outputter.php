<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Response;

use Psr\Http\Message\ResponseInterface;

/**
 * Simple outputter for a PSR-7 response.
 */
class Outputter
{
    /**
     * Output a response's headers and body.
     *
     * @param ResponseInterface $response
     * @return void
     */
    public function output(ResponseInterface $response): void
    {
        // Add puppet header
        // TODO middleware?
        // TODO add cookies
        $response = $response->withHeader('X-Puppet-Cert-Name', getenv('puppet_cert_name'));

        // Output headers
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        // Output response code (and reason phrase if overwritten)
        $reasonPhrase = $response->getReasonPhrase();
        if ($reasonPhrase === '') {
            http_response_code($response->getStatusCode());
        } else {
            header(
                sprintf(
                    'HTTP/%s %d %s',
                    $response->getProtocolVersion(),
                    $response->getStatusCode(),
                    $response->getReasonPhrase(),
                )
            );
        }

        // Output body
        // TODO consider streaming?
        echo $response->getBody();
    }
}
