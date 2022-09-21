<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec;

use DomainException;
use Schoolbox\External\Importer\Endpoint\IDSN;

use function count;

/**
 * Class DSN
 *
 * Encodes/decodes a dsn format for the Tass Api Endpoint configuration.
 * Tass uses three pieces to authenticate access to their api:
 *   api host, company code, app code
 *
 * But we only support two, so we combine them into one string
 *
 * The Tass API system is actually a coalition of smaller product API products.
 * Tass actually has multiple sets of credentials. Each set of credentials access
 * a smaller subset of this coalition of products. Fortunately they had the foresight
 * to format each set of credentials similarly. Therefore, we can assign a DSN_FORMAT
 * that applies to each set of credentials which are all supplied on one line in our ui
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint
 */
class DSN implements IDSN
{
    /**
     * Consts corresponding to each of the Tass systems
     */
    public const MODULE_LMS_INTEGRATION = 'lmsintegration';
    public const MODULE_STUDENT_DETAILS = 'studentdetails';
    public const MODULE_IDENTITY_MANAGEMENT = 'identitymanagement';
    public const MODULE_SCHOOL_CALENDAR_AND_NOTICES = 'schoolcalendarandnotices';
    public const MODULE_EMPLOYEE_HR = 'employeehr';


    /**
     * These modules are optional and may not be configured at all
     */
    private const MODULES_OPTIONAL = [
        self::MODULE_SCHOOL_CALENDAR_AND_NOTICES,
        self::MODULE_EMPLOYEE_HR
    ];


    /**
     * DSN format
     *
     * Product Module (e.g. Student Details API, Identity Management API, LMS Integration API)
     * followed by a colon
     * followed by key-value pairs
     * separated by comma
     */
    private const DSN_FORMAT = '/([^:]+):companycode=([^;]*);appcode=([^;]*);token=([^;,]*)/';


    /**
     * This character separates module-related tuples from each other
     */
    private const MODULE_SEPARATOR = ',';


    /**
     * This character separates module configuration tuples from each otehr
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
     * The expected key-value tuples per module
     * At the moment we expect three keys which should have three values
     */
    private const EXPECTED_TUPLE = ['companycode', 'appcode', 'token'];


    /**
     * Decode a dsn string
     *
     * @param string $dsn
     * @return array
     * @throws DomainException
     */
    public function decode(string $dsn): array
    {
        $bits = explode(self::OPTION_SEPARATOR, $dsn);
        $modules = $bits[0];
        $options = $bits[1] ?? null;

        $dsnParts = explode(self::MODULE_SEPARATOR, $modules);

        $struct = [
            'modules' => [],
            'options' => []
        ];

        /* Break apart module config
         */
        foreach ($dsnParts as $dsnPart) {
            $matches = [];
            preg_match(self::DSN_FORMAT, $dsnPart, $matches);

            $suppliedPieces = array_filter([
                $matches[2] ?? null,
                $matches[3] ?? null,
                $matches[4] ?? null
            ]);

            /* Only add the optional tuple if it was meaningful
             * If the optional tuple had no credentials, skip it
             */
            if (isset($matches[1]) && in_array($matches[1], self::MODULES_OPTIONAL, true) && !\count($suppliedPieces)) {
                continue;
            }

            /* Check the dsn format matches correctly
             */
            if (!count($suppliedPieces)) {
                throw new DomainException('TASS API DSN: could not decode; missing all credentials/bad format. Check company code, app code and token format');
            }

            /* Check that each module comes with the expected number of key-value pairs
             *
             * original dsn string + module + \count(expected_tuple) = 1 + 1 + 3 = 5
             */
            if (count($matches) !== count(self::EXPECTED_TUPLE) + 2) {
                throw new DomainException('TASS API DSN: could not decode; missing some credentials. Check company code, app code and token format');
            }

            $struct['modules'][$matches[1]] = [
                'companycode' => $matches[2],
                'appcode' => $matches[3],
                'token' => $matches[4]
            ];
        }

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
     * $parts consists of module, companycode, appcode, token strings and pairs
     *
     * @param array $parts
     * @return string
     */
    public function encode(array $parts): string
    {
        $struct = [];

        foreach ($parts['modules'] as $module => $pairs) {
            /* Skip encoding of the optional schoolcalendarandnotices
             * if none of its parts were meaningful
             */
            if (self::MODULE_SCHOOL_CALENDAR_AND_NOTICES === $module && !\count(array_filter([ $pairs['companycode'], $pairs['appcode'], $pairs['token'] ]))) {
                continue;
            }

            $module = "{$module}:";

            $tuples = [];

            foreach ($pairs as $key => $value) {
                $tuples[] = "{$key}={$value}";
            }

            $struct[] = $module . implode(self::MODULE_TUPLE_SEPARATOR, $tuples);
        }

        /* Encode option pieces
         */
        $options = [];

        foreach ($parts['options'] as $key => $value) {
            $options[] = "{$key}={$value}";
        }

        $moduleEncoding = implode(self::MODULE_SEPARATOR, $struct);
        $optionEncoding = implode(self::MODULE_TUPLE_SEPARATOR, $options);

        if (\count($options)) {
            return sprintf(
                '%s%s%s',
                $moduleEncoding,
                self::OPTION_SEPARATOR,
                $optionEncoding
            );
        }

        return $moduleEncoding;
    }
}
