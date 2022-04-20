<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Codec;

use DomainException;
use Schoolbox\External\Importer\Endpoint\IDSN;

use function count;

/**
 * Class DSN
 *
 * Encodes/decodes a dsn format for the Compass Api Endpoint configuration.
 *
 * We use timetableowner to simulate an actual user who owns the Compass Timetable Events
 * since that's mandatory in schoolbox atm. timetableowner is a user id from schoolbox.user
 *
 * This user account can be created by schools and elected using a dsn tuple
 *
 * @package Schoolbox\External\Connector\Compass\Base\Endpoint
 */
class DSN implements IDSN
{

    /**
     * DSN format
     *
     * key=...;timetableowner=...
     */
    private const DSN_FORMAT = '/key=([^;]+);timetableowner=([\d]+)/';


    /**
     * The expected key-value tuples
     */
    private const EXPECTED_TUPLE = ['key', 'timetableowner'];


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
            throw new DomainException('Compass API DSN: could not decode; missing all credentials/bad format.');
        }

        /* Check that each module comes with the expected number of key-value pairs
         *
         * original dsn string + \count(expected_tuple) = 1 + 2 = 3
         */
        if (count($matches) !== count(self::EXPECTED_TUPLE) + 1) {
            throw new DomainException('Compass API DSN: could not decode; missing some credentials.');
        }

        return [
            'key' => $matches[1],
            'timetableowner' => $matches[2],
        ];
    }


    /**
     * Encode a dsn string
     *
     * @param array $parts
     * @return string
     */
    public function encode(array $parts): string
    {
        return "key={$parts['key']};timetableowner={$parts['timetableowner']}";
    }
}
