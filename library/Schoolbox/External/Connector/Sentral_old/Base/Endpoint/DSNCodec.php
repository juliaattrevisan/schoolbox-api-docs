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
 * But we only support two, so we combine the host and tenant into one string
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint
 */
class DSNCodec
{

    /**
     * DSN format
     */
    private const DSN_FORMAT = '/host=([A-Za-z0-9\/:\.]+);tenant=([A-Za-z0-9]+)/';


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
            throw new DomainException('Sentral DSN: could not decode; check host and tenant format');
        }

        /* Check trailing slash in the host
         * Without it you just get Generic Error in a valid payload struct
         */
        if ('/' !== substr($matches[1], -1)) {
            throw new DomainException('Sentral DSN: host does not end with a forward slash /');
        }

        return [
            'host' => $matches[1],
            'tenant' => $matches[2]
        ];
    }


    /**
     * Encode a dsn string
     *
     * @param string $host
     * @param string $tenant
     * @return string
     */
    public function encode(string $host, string $tenant): string
    {
        return "host={$host};tenant={$tenant}";
    }
}
