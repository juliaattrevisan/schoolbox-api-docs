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
    private const DSN_FORMAT = '/schoolroute=([^;]+);clientid=([^;]+);clientsecret=([^;]+);portalaccessname=([^;]+);region=([^;]+)/';


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
    private const EXPECTED_TUPLE = ['schoolroute', 'clientid', 'clientsecret', 'portalaccessname', 'region'];


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
            'schoolroute' => null,
            'clientid' => null,
            'clientsecret' => null,
            'portalaccessname' => null,
            'region' => null,
            'options' => []
        ];

        $matches = [];
        preg_match(self::DSN_FORMAT, $credentials, $matches);

        /* Check the dsn format matches correctly
         */
        if (!count($matches)) {
            throw new DomainException('Veracross API DSN: could not decode; missing all credentials/bad format.');
        }

        /* Check that each module comes with the expected number of key-value pairs
         *
         * original dsn string + \count(expected_tuple) = 1 + 5 = 6
         */
        if (count($matches) !== count(self::EXPECTED_TUPLE) + 1) {
            throw new DomainException('Veracross API DSN: could not decode; missing some credentials.');
        }

        /* Pile into struct
         */
        $struct['schoolroute'] = $matches[1];
        $struct['clientid'] = $matches[2];
        $struct['clientsecret'] = $matches[3];
        $struct['portalaccessname'] = $matches[4];
        $struct['region'] = $matches[5];

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

                /* Not sure if this can actually happen, but as a precautionary measure, only try to inspect
                 * $matches if it contains something. It's not likely we'd have a count of options that fails to
                 * match the OPTION_FORMAT due to the strictness of dsn construction outside of here...but anyway
                 */
                if (!empty($matches)) {
                    $struct['options'][$matches[1]] = $matches[2];
                }
            }
        }

        return $struct;
    }


    /**
     * Encode a dsn string
     *
     * $parts consists of (school route, client id, client secret, portalaccessname, region) tuples
     *
     * @param array $parts
     * @return string
     */
    public function encode(#[SensitiveParameter] array $parts): string
    {
        $credentialsEncoding = "schoolroute={$parts['schoolroute']};clientid={$parts['clientid']};clientsecret={$parts['clientsecret']};portalaccessname={$parts['portalaccessname']};region={$parts['region']}";

        /* Encode option pieces
         */
        $options = [];

        foreach ($parts['options'] ?? [] as $key => $value) {
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
