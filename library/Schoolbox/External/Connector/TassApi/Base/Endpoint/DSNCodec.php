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
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint
 */
class DSNCodec
{

    /**
     * DSN format
     */
    private const DSN_FORMAT = '/companycode=([A-Za-z0-9]+);appcode=([A-Za-z0-9]+);token=([A-Za-z0-9=]+)/';


    /**
     * Decode a dsn string
     *
     * @param string $dsn
     * @return array
     * @throws DomainException
     */
    public function decode(string $dsn): array
    {
        preg_match(self::DSN_FORMAT, $dsn, $matches);

        if (!count($matches)) {
            throw new DomainException('Tass API DSN: could not decode; check company code, app code and token format');
        }

        return [
            'companycode' => $matches[1],
            'appcode' => $matches[2],
            'token' => $matches[3]
        ];
    }


    /**
     * Encode a dsn string
     *
     * @param string $companycode
     * @param string $appcode
     * @param string $token
     * @return string
     */
    public function encode(string $companycode, string $appcode, string $token): string
    {
        return "companycode={$companycode};appcode={$appcode};token={$token}";
    }
}
