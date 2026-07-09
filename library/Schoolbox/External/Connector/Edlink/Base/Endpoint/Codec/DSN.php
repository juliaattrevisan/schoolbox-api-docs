<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint\Codec;

use DomainException;
use Schoolbox\External\Importer\Endpoint\IDSN;
use SensitiveParameter;

use function count;

/**
 * Class DSN
 *
 * Edlink configuration requires one identifying piece:
 *  - Integration access token (non-expiring Bearer token from Edlink dashboard)
 *
 * @package Schoolbox\External\Connector\Edlink\Base\Endpoint\Codec
 */
class DSN implements IDSN
{
    /**
     * DSN format
     */
    private const DSN_FORMAT = '/token=([^;]+)/';

    /**
     * The expected key-value tuples
     */
    private const EXPECTED_TUPLE = ['token'];

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

        if (!count($matches)) {
            throw new DomainException('Edlink API DSN: could not decode; missing all credentials/bad format.');
        }

        if (count($matches) !== count(self::EXPECTED_TUPLE) + 1) {
            throw new DomainException('Edlink API DSN: could not decode; missing some credentials.');
        }

        return [
            'token' => $matches[1],
        ];
    }

    /**
     * Encode a dsn string
     *
     * @param array $parts
     * @return string
     */
    public function encode(#[SensitiveParameter] array $parts): string
    {
        return "token={$parts['token']}";
    }
}
