<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Wonde\Base\Endpoint\Codec;

use DomainException;
use Schoolbox\External\Importer\Endpoint\IDSN;
use SensitiveParameter;

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
     * This character separates module configuration tuples from each other
     */
    private const MODULE_TUPLE_SEPARATOR = ';';


    /**
     * This character separates module-related tuples from option tuples
     */
    private const OPTION_SEPARATOR = '?';


    /**
     * Optional params we permitted in SUDS Config
     */
    private const OPTION_FORMAT = '/([^;]+)=([^;]*)/';


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
    public function decode(#[SensitiveParameter] string $dsn): array
    {
        $bits = explode(self::OPTION_SEPARATOR, $dsn);

        $credentials = $bits[0];
        $options = $bits[1] ?? null;

        $struct = [
            'school' => null,
            'token' => null,
            'options' => []
        ];

        $matches = [];
        preg_match(self::DSN_FORMAT, $credentials, $matches);

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

        /* Pile into struct
         */
        $struct['school'] = $matches[1];
        $struct['token'] = $matches[2];

        /* Break apart optional config
         */
        if (null !== $options) {
            /* No tuples, but you gave an option separator; malformed DSN
             * Looks like there were supposed to be options, but none exist
             */
            if ('' === $options) {
                throw new DomainException('Detected option separator but no options found');
            }

            $dsnParts = explode(self::MODULE_TUPLE_SEPARATOR, $options);

            foreach ($dsnParts as $dsnPart) {
                $matches = [];
                preg_match(self::OPTION_FORMAT, $dsnPart, $matches);

                $struct['options'][$matches[1]] = $matches[2];
            }
        }

        return $struct;
    }


    /**
     * Encode a dsn string for a particular tass product
     *
     * $parts consists of (school, token) pairs
     *
     * @param array $parts
     * @return string
     */
    public function encode(#[SensitiveParameter] array $parts): string
    {
        $credentialsEncoding = "school={$parts['school']};token={$parts['token']}";

        /* Encode option pieces
         */
        $options = [];

        foreach ($parts['options'] as $key => $value) {
            $options[] = "{$key}={$value}";
        }

        $optionEncoding = implode(self::MODULE_TUPLE_SEPARATOR, $options);

        if (\count($options)) {
            return sprintf(
                '%s%s%s',
                $credentialsEncoding,
                self::OPTION_SEPARATOR,
                $optionEncoding
            );
        }

        return $credentialsEncoding;
    }
}
