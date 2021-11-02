<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint;

use DomainException;

/**
 * Class DSNCodec
 *
 * Encodes/decodes a dsn format for the Sentral Endpoint configuration.
 * Sentral uses three pieces to authenticate access to their api:
 *   api host, api tenant, api key
 *
 * But we only support two, so we combine the key and tenant into one string
 * With the $host, ensure it has a trailing slash; sentral hasn't figured out
 * how to tell you this is required
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint
 */
class DSNCodec
{

    /**
     * DSN format
     */
    private const DSN_FORMAT = '/key=([A-Za-z0-9]+);tenant=([A-Za-z0-9]+)/';


    /**
     * Decode a dsn string
     *
     * @param string $dsn
     * @return array
     * @throws DomainException
     */
    public function decode(string $dsn): array
    {
        preg_match(self::DSN_FORMAT, $dsn, $matches);

        if (!count($matches)) {
            throw new DomainException('Sentral DSN: could not decode; check key and tenant format');
        }

        return [
            'key' => $matches[1],
            'tenant' => $matches[2]
        ];
    }


    /**
     * Encode a dsn string
     *
     * @param string $key
     * @param string $tenant
     * @return string
     */
    public function encode(string $key, string $tenant): string
    {
        return "key={$key};tenant={$tenant}";
    }
}
