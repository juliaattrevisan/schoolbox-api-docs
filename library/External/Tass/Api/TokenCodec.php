<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Tass\Api;

use function base64_decode;
use function base64_encode;
use External\Tass\Api\Exception\InvalidApiKey;
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
class TokenCodec
{
    // The cipher method that openssl should use to encrypt/decrypt the token
    private const CIPHER_METHOD = 'aes-128-ecb';

    /**
     * @var string
     */
    private $decodedApiKey;


    /**
     * ApiToken constructor.
     *
     * @param string $apiKey
     * @throws InvalidApiKey If we can already tell that the API key is invalid
     */
    public function __construct(string $apiKey)
    {
        // Check not empty
        if (trim($apiKey) === '') {
            throw new InvalidApiKey('API key cannot be empty');
        }

        // Check no invalid chars
        $decodedApiKey = base64_decode($apiKey, true);
        if ($decodedApiKey === false) {
            throw new InvalidApiKey("Unable to base64_decode API key: '$apiKey'");
        }

        // Check is not inconsistent with base64 encoding
        $reEncoded = base64_encode($decodedApiKey);
        if ($reEncoded !== $apiKey) {
            throw new InvalidApiKey("encode(decode(key)) !== key. Key: '$apiKey', ReEncoded: '$reEncoded'");
        }

        $this->decodedApiKey = $decodedApiKey;
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
        $encrypted  = openssl_encrypt($parameters, self::CIPHER_METHOD, $this->decodedApiKey, OPENSSL_RAW_DATA);

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
        $parameters = openssl_decrypt($encrypted, self::CIPHER_METHOD, $this->decodedApiKey, OPENSSL_RAW_DATA);

        return json_decode($parameters, true);
    }
}
