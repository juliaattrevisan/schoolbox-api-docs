<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec;

use External\Tass\Api\Exception\InvalidApiKey;
use SensitiveParameter;

use function base64_decode;
use function base64_encode;
use function json_decode;
use function json_encode;
use function openssl_decrypt;
use function openssl_encrypt;
use function strlen;
use function trim;

/**
 * Encapsulates API token generation and unpacking
 *
 * @see https://github.com/TheAlphaSchoolSystemPTYLTD/api-introduction/blob/master/encryptDecrypt.php
 */
class Token
{
    /**
     * The cipher method for a 16-byte (128-bit) key, used by the original
     * 24-character base64 TASS tokens.
     */
    private const CIPHER_METHOD_128 = 'aes-128-ecb';

    /**
     * The cipher method for a 32-byte (256-bit) key, used by the TASS tokens
     * introduced in the May 2026 release (44-character base64).
     */
    private const CIPHER_METHOD_256 = 'aes-256-ecb';


    /**
     * @var string
     */
    private string $decodedToken;

    /**
     * The cipher method openssl should use to encrypt/decrypt the token,
     * selected from the decoded key length.
     *
     * @var string
     */
    private string $cipherMethod;


    /**
     * ApiToken constructor.
     *
     * @param string $token
     * @throws InvalidApiKey If we can already tell that the API key is invalid
     */
    public function __construct(#[SensitiveParameter] string $token)
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

        /* Select the cipher method from the decoded key length rather than the
         * visible token string length, since the key length is the actual
         * cryptographic distinction. TASS's May 2026 release moved newly
         * generated tokens from a 16-byte key (24-char base64, aes-128-ecb) to a
         * 32-byte key (44-char base64, aes-256-ecb).
         */
        $keyLength = strlen($decodedToken);

        $this->cipherMethod = match ($keyLength) {
            16 => self::CIPHER_METHOD_128,
            32 => self::CIPHER_METHOD_256,
            default => throw new InvalidApiKey(
                "API token decodes to an unsupported key length of $keyLength bytes; expected 16 or 32"
            ),
        };

        $this->decodedToken = $decodedToken;
    }


    /**
     * Returns an encrypted token (not URL encoded) composed of all the method arguments.
     *
     * Optionally elect to use JSON_PRETTY_PRINT which cleans up newlines etc. and formats
     * your json data in a specific way
     *
     * @param array $data
     * @param bool  $prettyPrint
     * @return string
     */
    public function generateToken(array $data, bool $prettyPrint): string
    {
        /* For some records in some tass servers, JSON_PRETTY_PRINT is required.
         * For other records, you must NOT use it.
         *
         * Depending on the record, if you use the wrong PRETTY_PRINT encoding,
         * you'll get a 401 Unauthorized.
         *
         * Yes, it's insanity.
         */
        if ($prettyPrint) {
            $parameters = json_encode($data, JSON_PRETTY_PRINT);
        } else {
            $parameters = json_encode($data);
        }

        $encrypted = openssl_encrypt($parameters, $this->cipherMethod, $this->decodedToken, OPENSSL_RAW_DATA);

        return base64_encode($encrypted);
    }


    /**
     * Returns the token's parameter payload
     *
     * @param string $token
     * @return array
     */
    public function unpackToken(#[SensitiveParameter] string $token): array
    {
        $encrypted  = base64_decode($token);
        $parameters = openssl_decrypt($encrypted, $this->cipherMethod, $this->decodedToken, OPENSSL_RAW_DATA);

        /* Couldn't decrypt the $encrypted data for some reason
         * Might as well log it
         */
        if (false === $parameters) {
            trigger_error(
                sprintf(
                    'SUDS TASS Token: could not decrypt token %s properly. This is the reason: %s',
                    $token,
                    openssl_error_string()
                ),
                E_USER_ERROR
            );
        }

        return json_decode($parameters, true);
    }
}
