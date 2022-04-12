<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Wonde\Base\Endpoint\Codec;

use DomainException;
use Schoolbox\External\Importer\Endpoint\IDSN;

use function count;

/**
 * Class DSN
 *
 * Wonde configuration requires two identifying pieces:
 *  - School id, in the format A1234567...
 *  - Authorization token
 *
 * The school id is used in every endpoint request.
 *
 * @package Schoolbox\External\Connector\Wonde\Base\Endpoint
 */
class DSN implements IDSN
{

    /**
     * DSN format
     */
    private const DSN_FORMAT = '/school=([^;]+);token=([^;]+)/';


    /**
     * The expected key-value tuples
     */
    private const EXPECTED_TUPLE = ['school', 'token'];


    /**
     * Decode a dsn string
     *
     * @param string $dsn
     * @return array
     * @throws DomainException
     */
    public function decode(string $dsn): array
    {
        $matches = [];
        preg_match(self::DSN_FORMAT, $dsn, $matches);

        /* Check the dsn format matches correctly
         */
        if (!count($matches)) {
            throw new DomainException('Wonde API DSN: could not decode; missing all credentials/bad format.');
        }

        /* Check that each module comes with the expected number of key-value pairs
         *
         * original dsn string + \count(expected_tuple) = 1 + 2 = 3
         */
        if (count($matches) !== count(self::EXPECTED_TUPLE) + 1) {
            throw new DomainException('Wonde API DSN: could not decode; missing some credentials.');
        }

        return [
            'school' => $matches[1],
            'token' => $matches[2],
        ];
    }


    /**
     * Encode a dsn string for a particular tass product
     *
     * $parts consists of (school, token) pairs
     *
     * @param array $parts
     * @return string
     */
    public function encode(array $parts): string
    {
        return "school={$parts['school']};token={$parts['token']}";
    }
}
