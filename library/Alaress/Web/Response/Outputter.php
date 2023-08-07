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
     * Amount of bytes to read at a time when outputting the response body.
     *
     * This is currently an arbitrary value of 10000 bytes.
     */
    private const RESPONSE_BODY_READ_BYTES = 10000;

    /**
     * Output a response's headers and body.
     *
     * @param ResponseInterface $response
     * @return void
     */
    public function output(ResponseInterface $response): void
    {
        // Add puppet header, if it's there
        // TODO move to middleware once outputAuraContent is a request handler:
        //      could apply this header after handler is called
        $certName = getenv('puppet_cert_name');
        if (is_string($certName)) {
            $response = $response->withHeader('X-Puppet-Cert-Name', getenv('puppet_cert_name'));
        }

        // Basic CSP, mimicking what currently exists on on-prem instances.
        // TODO as above: move to middleware
        $response = $response->withHeader('Content-Security-Policy', 'frame-ancestors \'self\'');

        // Output headers
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value));
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

        // Now output the body stream
        $body = $response->getBody();

        // Rewind the body stream if possible.
        // Some request bodies created by other libraries are not rewound:
        // other request bodies (e.g. `TwigCallbackStream`) are not seekable at
        // all, so accommodate both cases.
        if ($body->isSeekable()) {
            $body->rewind();
        }

        // Finally: output body stream in chunks, so that the entire body is not
        // loaded into memory at once only to output it.
        // Important when streaming large content (e.g. logs): see SA-413.
        while (!$body->eof()) {
            echo $body->read(self::RESPONSE_BODY_READ_BYTES);
        }
    }
}
