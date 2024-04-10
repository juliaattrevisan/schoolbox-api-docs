<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Firebase\JWT\Key;
use GuzzleHttp\Client;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Oidc\Server\RelyingParty;
use Schoolbox\User;
use Schoolbox\UserMapper;
use SensitiveParameter;

/**
 * Parser for oAuth tokens issued to a client by this Schoolbox instance, and
 * then sent back to this instance.
 */
class OauthClientHeld extends Base
{
    public function __construct(
        private UserMapper $userMapper,
        private IConfigLoader $config,
        private RelyingParty\Mapper $relyingPartyMapper
    ) {
    }



    public function accepts(#[SensitiveParameter] string $jwt): bool
    {
        $claims    = $this->decodeWithoutVerification($jwt);
        $audiences = (array)($claims[self::CLAIM_AUD] ?? []);
        if (empty($audiences)) {
            return false;
        }
        return $this->relyingPartyMapper->countByColumnList(['client_id' => $audiences]) > 0;
    }

    public function determineUser(#[SensitiveParameter] string $jwt): User
    {
        // Is there an actual subject or other claims in this JWT?
        // If so, then it should be extracted: otherwise, fall back to the
        // client credentials user.
        $claims = $this->decode(
            $jwt,
            new Key($this->config->get(ConfigMapper::NAME_JWT_PUBLIC_KEY), Jwt::SIGNING_ALGORITHM),
            (int)$this->config->get(ConfigMapper::NAME_JWT_LEEWAY),
        );

        $audiences = (array)($claims[self::CLAIM_AUD] ?? []);
        foreach ($audiences as $audience) {
            $relyingParty = $this->relyingPartyMapper->findByClientId($audience);
            if ($relyingParty->getClientCredentialsUser()) {
                return $relyingParty->getClientCredentialsUser();
            }
        }

        // How do I know if this was made by client_credentials though?

        throw new Exception\InvalidIdentity('Nobody is here yet');
    }
}
