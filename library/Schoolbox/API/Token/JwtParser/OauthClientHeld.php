<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Firebase\JWT\Key;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Oidc\Server\RelyingParty;
use Schoolbox\User;
use Schoolbox\UserMapper;
use SensitiveParameter;

/**
 * Parser for oAuth access tokens issued to a known client (a.k.a. relying
 * party) by this Schoolbox instance, as a result of that client making an
 * authentication request against this instance.
 *
 * That request may have been made either:
 * * as an `authorization_code` grant: in this case the `sub` claim will contain
 *   the username of the user to be authenticated
 * * as a `client_credentials` grant: in this case there will be no `sub` claim,
 *   and the user to authenticate will be extracted from the client
 *   configuration.
 *
 * TODO consider merging with `SelfIssued` due to the amount of common code
 */
class OauthClientHeld extends Base
{
    /**
     * Constructor
     *
     * @param UserMapper          $userMapper         User mapper: required for fetching users based on their username.
     * @param IConfigLoader       $config             Configuration store.
     * @param RelyingParty\Mapper $relyingPartyMapper Relying party mapper: required for verifying that the client is
     *                                                known to this instance, and also for determining the user if the
     *                                                access token contains no subject.
     */
    public function __construct(
        UserMapper $userMapper,
        IConfigLoader $config,
        private RelyingParty\Mapper $relyingPartyMapper
    ) {
        parent::__construct($userMapper, $config);
    }

    /**
     * This JWT will be accepted if its audience is known to this instance.
     *
     * @param string $jwt
     * @return bool
     */
    public function accepts(#[SensitiveParameter] string $jwt): bool
    {
        $claims    = $this->decodeWithoutVerification($jwt);
        $audiences = (array)($claims[self::CLAIM_AUD] ?? []);
        if (empty($audiences)) {
            return false;
        }
        return $this->relyingPartyMapper->countByColumnList(['client_id' => $audiences]) > 0;
    }

    /**
     * This JWT's user will be:
     * * the `sub` claim, if it's there: it will be, for access tokens generated
     *   via an `authorization_code` grant
     * * otherwise, the access token's client's configured client credentials
     *   user: assume that any access token without a subject was made via a
     *   `client_credentials` grant
     *
     * @param string $jwt
     * @return User
     */
    public function determineUser(#[SensitiveParameter] string $jwt): User
    {
        $claims = $this->decode(
            $jwt,
            new Key($this->config->get(ConfigMapper::NAME_JWT_PUBLIC_KEY), Jwt::SIGNING_ALGORITHM),
            (int)$this->config->get(ConfigMapper::NAME_JWT_LEEWAY),
        );

        // TODO validate JTI?
        // TODO perform other validation from SelfIssued here
        if ($claims[self::CLAIM_SUB]) {
            return $this->findUser($claims[self::CLAIM_SUB], self::CLAIM_SUB);
        }

        $audiences = (array)($claims[self::CLAIM_AUD] ?? []);
        foreach ($audiences as $audience) {
            $relyingParty = $this->relyingPartyMapper->findByClientId($audience);
            if ($relyingParty?->getClientCredentialsUser()) {
                return $relyingParty->getClientCredentialsUser();
            }
        }
        throw new Exception\InvalidIdentity(
            sprintf(
                'JWT "%s" claim does not exist, and "%s" contains no audience with a client credentials user',
                self::CLAIM_SUB,
                self::CLAIM_AUD,
            )
        );
    }
}
