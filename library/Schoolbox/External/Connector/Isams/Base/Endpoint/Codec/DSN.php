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
     * These correspond to the modules we found by configuring the DSN
     * (Each module is an array key in the decoded DSN)
     */
    public const MODULE_BATCHXML = 'batch';
    public const MODULE_STAFFPHOTO = 'staffphoto';
    public const MODULE_STUDENTPHOTO = 'studentphoto';


    /**
     * DSN format
     *
     * iSAMS Module (e.g. Batch XML, Staff Photos, Student Photos)
     * followed by a colon
     * followed by key-value pairs
     * separated by comma
     */
    private const DSN_FORMAT = '/([^:]+):key=([^;]*)/';


    /**
     * This character separates module-related tuples from each other
     */
    private const MODULE_SEPARATOR = ',';


    /**
     * This character separates module configuration tuples from each other
     */
    private const MODULE_TUPLE_SEPARATOR = ';';


    /**
     * Decode a DSN string
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

        $dsnParts = explode(self::MODULE_SEPARATOR, $dsn);

        $struct = [
            'modules' => []
        ];

        /* Break apart module config
         */
        foreach ($dsnParts as $dsnPart) {
            $matches = [];
            preg_match(self::DSN_FORMAT, $dsnPart, $matches);

            $struct['modules'][$matches[1]] = [
                'key' => $matches[2]
            ];
        }

        return $struct;
    }


    /**
     * Encode a DSN string
     *
     * @param array $parts
     * @return string
     */
    public function encode(array $parts): string
    {
        $struct = [];

        foreach ($parts['modules'] as $module => $pairs) {
            $module = "{$module}:";

            $tuples = [];

            foreach ($pairs as $key => $value) {
                $tuples[] = "{$key}={$value}";
            }

            $struct[] = $module . implode(self::MODULE_TUPLE_SEPARATOR, $tuples);
        }

        return implode(self::MODULE_SEPARATOR, $struct);
    }
}
