<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec;

use External\Tass\Api\Exception\InvalidApiKey;

use function base64_decode;
use function base64_encode;
use function json_decode;
use function json_encode;
use function openssl_decrypt;
use function openssl_encrypt;
use function trim;

/**
 * Encapsulates API token generation and unpacking
 *
 * @see https://github.com/TheAlphaSchoolSystemPTYLTD/api-introduction/blob/master/encryptDecrypt.php
 */
class Token
{
    /**
     * The cipher method that openssl should use to encrypt/decrypt the token
     */
    private const CIPHER_METHOD = 'aes-128-ecb';


    /**
     * @var string
     */
    private string $decodedToken;


    /**
     * ApiToken constructor.
     *
     * @param string $token
     * @throws InvalidApiKey If we can already tell that the API key is invalid
     */
    public function __construct(string $token)
    {
        // Check not empty
        if (trim($token) === '') {
            throw new InvalidApiKey('API token cannot be empty');
        }

        // Check no invalid chars
        $decodedToken = base64_decode($token, true);

        if ($decodedToken === false) {
            throw new InvalidApiKey("Unable to base64_decode API token: '$token'");
        }

        // Check is not inconsistent with base64 encoding
        $reEncoded = base64_encode($decodedToken);

        if ($reEncoded !== $token) {
            throw new InvalidApiKey("encode(decode(token)) !== token. Token: '$token', ReEncoded: '$reEncoded'");
        }

        $this->decodedToken = $decodedToken;
    }


    /**
     * Returns an encrypted token (not URL encoded) composed of all the method arguments.
     *
     * @param array $data
     * @return string
     */
    public function generateToken(array $data): string
    {
        $parameters = json_encode($data);

        if (isset($data['code']) && $data['code'] == 'CARR001') {
            print_r($parameters);
        }

        if (isset($data['student_code']) && $data['student_code'] == 'CARR001') {
            print_r($parameters);
        }

        $encrypted  = openssl_encrypt($parameters, self::CIPHER_METHOD, $this->decodedToken, OPENSSL_RAW_DATA);

        return base64_encode($encrypted);
    }


    /**
     * Returns the token's parameter payload
     *
     * @param string $token
     * @return array
     */
    public function unpackToken(string $token): array
    {
        $encrypted  = base64_decode($token);
        $parameters = openssl_decrypt($encrypted, self::CIPHER_METHOD, $this->decodedToken, OPENSSL_RAW_DATA);

        return json_decode($parameters, true);
    }
}
