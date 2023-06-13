<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Codec;

use DomainException;
use Schoolbox\External\Importer\Endpoint\IDSN;
use SensitiveParameter;

/**
 * Class DSN
 *
 * Encodes/decodes a DSN format for the Sentral Endpoint configuration.
 * Sentral uses three pieces to authenticate access to their api:
 *   api host, api tenant, api key
 *
 * But we only support two, so we combine the key and tenant into one string
 * With the $host, ensure it has a trailing slash; sentral hasn't figured out
 * how to tell you this is required
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint\Codec
 */
class DSN implements IDSN
{
    /**
     * This character separates tuples from each other
     */
    private const TUPLE_SEPARATOR = ';';


    /**
     * This character separates credentials-related tuples from option tuples
     */
    private const OPTION_SEPARATOR = '?';


    /**
     * Optional params we permitted in SUDS Config
     */
    private const OPTION_FORMAT = '/([^;]+)=([^;]*)/';


    /**
     * DSN format
     */
    private const DSN_FORMAT = '/key=([A-Za-z0-9]+);tenant=([A-Za-z0-9]+)/';


    /**
     * Perform a special transformation on this option's value
     * which we know to be a csv
     */
    private const OPTION_CSV = ['parentRelationIds'];


    /**
     * Decode a dsn string
     *
     * @param string $dsn
     * @return array
     * @throws DomainException
     */
    public function decode(#[SensitiveParameter] string $dsn): array
    {
        $bits = explode(self::OPTION_SEPARATOR, $dsn);
        $credentials = $bits[0];
        $options = $bits[1] ?? null;

        preg_match(self::DSN_FORMAT, $credentials, $matches);

        /* You didn't specify key/tenant properly somehow
         */
        if (!count($matches)) {
            throw new DomainException('Sentral DSN: could not decode; check key and tenant format');
        }

        $struct = [
            'key' => $matches[1],
            'tenant' => $matches[2],
            'options' => []
        ];

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

            $dsnParts = explode(self::TUPLE_SEPARATOR, $options);

            foreach ($dsnParts as $dsnPart) {
                $matches = [];
                if (preg_match(self::OPTION_FORMAT, $dsnPart, $matches)) {
                    /* Automatically unpack parentRelationIds into a useful array
                     * Also accommodates other fields which might be csvs
                     * (just add these to the const)
                     */
                    if (in_array($matches[1], self::OPTION_CSV, true)) {
                        $struct['options'][$matches[1]] = explode(',', $matches[2]);
                    } else {
                        $struct['options'][$matches[1]] = $matches[2];
                    }
                }
            }
        }

        return $struct;
    }


    /**
     * Encode a dsn string
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

        return "key={$parts['key']};tenant={$parts['tenant']}{$optionEncoding}";
    }
}
