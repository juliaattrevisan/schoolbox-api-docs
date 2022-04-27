<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Codec;

use Schoolbox\External\Importer\Endpoint\IDSN;

/**
 * Class DSN
 *
 * Encodes/decodes a dsn format for the Compass Api Endpoint configuration.
 * Compass now only uses an api key, since its move to SUDS Timetable, and
 * no longer requires schools to specify a timetable owner.
 *
 * @package Schoolbox\External\Connector\Compass\Base\Endpoint
 */
class DSN implements IDSN
{

    /**
     * Decode a dsn string
     *
     * @param string $dsn
     * @return array
     */
    public function decode(string $dsn): array
    {
        return [
            'key' => $dsn
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
        return $parts['key'];
    }
}
