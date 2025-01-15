<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Codec;

use DomainException;
use Schoolbox\External\Importer\Endpoint\IDSN;
use SensitiveParameter;

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
    public const MODULE_BATCHXML = 'batchxml';
    public const MODULE_RESTAPI = 'restapi';


    /**
     * Each module const has a corresponding path which isn't conveniently known,
     * so I just append them when decoding and ignore them when encoding DSNs
     */
    public const PATHS = [
        self::MODULE_BATCHXML => 'xml.ashx',
    ];


    /**
     * This character separates tuples from each other
     */
    private const TUPLE_SEPARATOR = ';';


    /**
     * This character separates credentials-related tuples from option tuples
     */
    private const OPTION_SEPARATOR = '?';


    /**
     * This character separates module-related tuples from each other
     */
    private const MODULE_SEPARATOR = ',';


    /**
     * This character separates module configuration tuples from each other
     */
    private const MODULE_TUPLE_SEPARATOR = ';';


    /**
     * Optional params we permitted in SUDS Config
     */
    private const OPTION_FORMAT = '/([^;]+)=([^;]*)/';


    /**
     * DSN format
     *
     * iSAMS Module (e.g. Batch XML, Staff Photos, Student Photos)
     * followed by a colon
     * followed by key-value pairs
     * separated by comma
     *
     * Note: somewhat obsoleted by isams needing different data structs
     * for storing batchxml and restapi credentials differently.
     */
    private const DSN_FORMAT = '/([^:]+):[([^=]+)=([^;]*)]*/';


    /**
     * Decode a DSN string
     *
     * @param string $dsn
     * @return array
     * @throws DomainException
     */
    public function decode(#[SensitiveParameter] string $dsn): array
    {
        if ('' === trim($dsn)) {
            throw new DomainException('iSAMS API DSN: could not decode; missing some credentials.');
        }

        $bits = explode(self::OPTION_SEPARATOR, $dsn);
        $credentials = $bits[0];
        $options = $bits[1] ?? null;

        $struct = [
            'modules' => [],
            'options' => []
        ];

        /* Break apart module config
         */
        $modules = explode(self::MODULE_SEPARATOR, $credentials);

        foreach ($modules as $module) {
            /* Snap the module's configuration tuples out
             *
             * Note that the restapi host contains a :, because it's a url with
             * https:// scheme out the front there. Treat it a bit specially
             */
            $moduleParts = explode(':', $module, 2);

            /* Tuples are in $moduleParts[1] and the module name itself
             * is in $moduleParts[0]
             */
            $dsnParts = explode(self::TUPLE_SEPARATOR, $moduleParts[1]);

            foreach ($dsnParts as $dsnPart) {
                $matches = [];
                preg_match(self::OPTION_FORMAT, $dsnPart, $matches);

                $struct['modules'][$moduleParts[0]][$matches[1]] = $matches[2];
            }
        }

        /* Have a look at the options which may or may not exist
         * These options might have been introduced *after* the dsn was last saved
         * so those array elements might not exist yet. The user needs to save
         * the form to re-encode their dsn accordingly.
         */
        if (null !== $options) {
            /* No tuples, but you gave an option separator; malformed DSN
             * Looks like there were supposed to be options, but none exist
             */
            if ('' === $options) {
                throw new DomainException('Detected option separator but no options found');
            }

            preg_match(self::OPTION_FORMAT, $options, $matches);

            $struct['options'][$matches[1]] = $matches[2];
        }

        return $struct;
    }


    /**
     * Encode a DSN string
     *
     * @param array $parts
     * @return string
     */
    public function encode(#[SensitiveParameter] array $parts): string
    {
        /* Encode option pieces
         */
        $options = [];

        /* Arrays are serialized into a csv
         */
        foreach (($parts['options'] ?? []) as $key => $value) {
            if (is_array($value)) {
                $imploded = implode(',', $value);

                $options[] = "{$key}={$imploded}";
            } else {
                $options[] = "{$key}={$value}";
            }
        }

        // Only include options if some were set
        $optionEncoding = '';

        if (count($options)) {
            $optionEncoding = '?' . implode(self::TUPLE_SEPARATOR, $options);
        }

        $struct = [];

        foreach ($parts['modules'] as $module => $pairs) {
            $module = "{$module}:";

            $tuples = [];

            foreach ($pairs as $key => $value) {
                $tuples[] = "{$key}={$value}";
            }

            $struct[] = $module . implode(self::MODULE_TUPLE_SEPARATOR, $tuples);
        }

        return implode(self::MODULE_SEPARATOR, $struct) . $optionEncoding;
    }
}
