<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token;

use Alaress_Date_Format;
use Firebase\JWT\JWT as JwtLibrary;
use Schoolbox\API\Token\JwtParser\IParser;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Url\Factory;
use Schoolbox\User;

/**
 * JWT generator and validator.
 */
class Jwt
{
    /**
     * Signing algorithm for JWTs.
     */
    public const SIGNING_ALGORITHM = 'RS256';
    public const KEY_ID            = 'schoolbox';


    /**
     * Config settings are stored here.
     */
    private IConfigLoader $config;


    /**
     * Constructor
     *
     * @param IConfigLoader $configLoader
     */
    public function __construct(IConfigLoader $configLoader)
    {
        $this->config = $configLoader;
    }


    /**
     * Generates a JWT for this user.
     *
     * @param User                     $user
     * @param Alaress_Date_Format|null $expiry           If provided, the JWT expires after this
     * @param string|null              $jwtId
     *                                   many seconds.
     * @param array                    $additionalClaims Any extra claims to make beyond the
     *                                   standard 'iss', 'sub', 'exp'. 'jti'
     * @return string
     */
    public function generate(User $user, ?Alaress_Date_Format $expiry = null, ?string $jwtId = null, array $additionalClaims = []): string
    {
        $schoolboxFqdn = (new Factory($this->config))->buildFullyQualified()->getHost();

        // Issuer:     this Schoolbox instance's FQDN
        // Subject:    $user
        // Expiry:     optional
        // Not before: not used for now
        // May add ability to regenerate tokens later?
        $claims = [
            IParser::CLAIM_ISS => $schoolboxFqdn,
            IParser::CLAIM_SUB => $user->getUsername(),
        ];
        if ($expiry !== null) {
            $claims[IParser::CLAIM_EXP] = $expiry->getUnixTimeStamp();
        }
        if ($jwtId !== null) {
            $claims[IParser::CLAIM_JTI] = $jwtId;
        }
        return JwtLibrary::encode(
            array_merge($claims, $additionalClaims),
            $this->config->get(ConfigMapper::NAME_JWT_PRIVATE_KEY),
            self::SIGNING_ALGORITHM,
            self::KEY_ID
        );
    }

    /**
     * Generates a JWKS array for this Schoolbox instance.
     *
     * @return array
     */
    public function getJwks(): array
    {
        $key = openssl_pkey_get_details(
            openssl_pkey_get_public($this->config->get(ConfigMapper::NAME_JWT_PUBLIC_KEY))
        );
        return [
            'keys' => [
                [
                    'kty' => "RSA",
                    'n'   => rtrim(strtr(base64_encode($key['rsa']['n']), '+/', '-_'), '='),
                    'e'   => rtrim(strtr(base64_encode($key['rsa']['e']), '+/', '-_'), '='),
                    'kid' => self::KEY_ID,
                    'alg' => self::SIGNING_ALGORITHM,
                ],
            ],
        ];
    }
}
