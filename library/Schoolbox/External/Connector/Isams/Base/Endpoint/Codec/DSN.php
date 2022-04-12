<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Codec;

use DomainException;
use Schoolbox\External\Importer\Endpoint\IDSN;

/**
 * Class DSN
 *
 * The api key provided is the only data isams needs; no mutations required
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint
 */
class DSN implements IDSN
{

    /**
     * Decode a dsn string
     *
     * @param string $dsn
     * @return array
     * @throws DomainException
     */
    public function decode(string $dsn): array
    {
        if ('' === trim($dsn)) {
            throw new DomainException('iSAMS API DSN: could not decode; missing some credentials.');
        }

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
