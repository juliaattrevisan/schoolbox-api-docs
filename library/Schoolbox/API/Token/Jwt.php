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
        // Issuer:     this Schoolbox instance's FQDN
        // Subject:    $user
        // Expiry:     optional
        // Not before: not used for now
        // May add ability to regenerate tokens later?
        $claims = [
            IParser::CLAIM_ISS => $this->getSchoolboxIssuer(),
            IParser::CLAIM_SUB => $user->getUsername(),
        ];
        if ($expiry !== null && !$expiry->isNull()) {
            $claims[IParser::CLAIM_EXP] = $expiry->getUnixTimeStamp();
        }
        if ($jwtId !== null) {
            $claims[IParser::CLAIM_JTI] = $jwtId;
        }
        return JwtLibrary::encode(
            array_merge($claims, $additionalClaims),
            $this->config->get(ConfigMapper::NAME_JWT_PRIVATE_KEY),
            self::SIGNING_ALGORITHM
        );
    }

    /**
     * @return string|null
     */
    private function getSchoolboxIssuer(): ?string
    {
        return (new Factory($this->config))->buildFullyQualified()->getHost();
    }
}
