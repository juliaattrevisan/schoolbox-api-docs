<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint;

use DomainException;

/**
 * Class DSNCodec
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
class DSNCodec
{

    /**
     * DSN format
     *
     * Product Module (e.g. Student Details API, Identity Management API, LMS Integration API)
     * followed by a colon
     * followed by key-value pairs
     * separated by comma
     */
    private const DSN_FORMAT = '/([a-zA-Z0-9]+):companycode=([A-Za-z0-9]+);appcode=([A-Za-z0-9]+);token=([A-Za-z0-9=]+)/';


    /**
     * Decode a dsn string
     *
     * @param string $dsn
     * @return array
     * @throws DomainException
     */
    public function decode(string $dsn): array
    {
        $dsnParts = explode(',', $dsn);
        $struct = [];

        foreach ($dsnParts as $dsnPart) {
            $matches = [];
            preg_match(self::DSN_FORMAT, $dsnPart, $matches);

            if (!count($matches)) {
                throw new DomainException('Tass API DSN: could not decode; check company code, app code and token format');
            }

            $struct[$matches[1]] = [
                'companycode' => $matches[2],
                'appcode' => $matches[3],
                'token' => $matches[4]
            ];
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

        foreach ($parts as $module => $pairs) {
            $module = "{$module}:";

            $tuples = [];

            foreach ($pairs as $key => $value) {
                $tuples[] = "{$key}={$value}";
            }

            $struct[] = $module . implode(';', $tuples);
        }

        return implode(',', $struct);
    }
}
