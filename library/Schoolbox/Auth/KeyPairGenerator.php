<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Auth;

class KeyPairGenerator
{
    public static function generate(): array
    {
        // Build key pair
        $res = openssl_pkey_new([
                                    'digest_alg'       => 'SHA512',
                                    'private_key_bits' => 2048,
                                    'private_key_type' => OPENSSL_KEYTYPE_RSA,
                                ]);
        openssl_pkey_export($res, $privateKey);
        $keyDetails = openssl_pkey_get_details($res);
        $publicKey  = $keyDetails['key'];

        return [
            'private' => $privateKey,
            'public'  => $publicKey
        ];
    }
}
