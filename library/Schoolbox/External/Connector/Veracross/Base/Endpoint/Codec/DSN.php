<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Codec;

use DomainException;
use Schoolbox\External\Importer\Endpoint\IDSN;
use SensitiveParameter;

use function count;

/**
 * Class DSN
 *
 * Veracross configuration requires two identifying pieces:
 *  - School route, which is used as part of the endpoint's host url
 *  - Client Id, provided by the Veracross guys
 *  - Client Secret, also provided by the Veracross guys
 *  - Portal Access Name, chosen by schools to fulfil attendance urls
 *
 * School Route is used in every request, but client id and client secret
 * are used to periodically generate access tokens
 *
 * @package Schoolbox\External\Connector\Veracross\Base\Endpoint
 */
class DSN implements IDSN
{
    /**
     * DSN format
     */
    private const DSN_FORMAT = '/schoolroute=([^;]+);clientid=([^;]+);clientsecret=([^;]+);portalaccessname=([^;]+)/';


    /**
     * The expected key-value tuples
     */
    private const EXPECTED_TUPLE = ['schoolroute', 'clientid', 'clientsecret', 'portalaccessname'];


    /**
     * Decode a dsn string
     *
     * @param string $dsn
     * @return array
     * @throws DomainException
     */
    public function decode(#[SensitiveParameter] string $dsn): array
    {
        $matches = [];
        preg_match(self::DSN_FORMAT, $dsn, $matches);

        /* Check the dsn format matches correctly
         */
        if (!count($matches)) {
            throw new DomainException('Veracross API DSN: could not decode; missing all credentials/bad format.');
        }

        /* Check that each module comes with the expected number of key-value pairs
         *
         * original dsn string + \count(expected_tuple) = 1 + 4 = 5
         */
        if (count($matches) !== count(self::EXPECTED_TUPLE) + 1) {
            throw new DomainException('Veracross API DSN: could not decode; missing some credentials.');
        }

        return [
            'schoolroute' => $matches[1],
            'clientid' => $matches[2],
            'clientsecret' => $matches[3],
            'portalaccessname' => $matches[4],
        ];
    }


    /**
     * Encode a dsn string
     *
     * $parts consists of (school route, client id, client secret) tuples
     *
     * @param array $parts
     * @return string
     */
    public function encode(#[SensitiveParameter] array $parts): string
    {
        return "schoolroute={$parts['schoolroute']};clientid={$parts['clientid']};clientsecret={$parts['clientsecret']};portalaccessname={$parts['portalaccessname']}";
    }
}
