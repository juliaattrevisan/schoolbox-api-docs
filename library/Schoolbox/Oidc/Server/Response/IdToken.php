<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\Response;

use Lcobucci\JWT\Builder;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use OpenIDConnectServer\IdTokenResponse;
use Schoolbox\API\Token\Jwt;

/**
 * Customised `\OpenIDConnectServer\IdTokenResponse` for Schoolbox's needs.
 */
class IdToken extends IdTokenResponse
{
    /**
     * Adds Schoolbox's OIDC client's required claims to the builder:
     * * "kid" (key ID) equal to `Jwt::KEY_ID`
     * * "nonce" equal to whatever was passed in the query string
     *
     * @param AccessTokenEntityInterface $accessToken
     * @param UserEntityInterface        $userEntity
     * @return Builder
     */
    protected function getBuilder(AccessTokenEntityInterface $accessToken, UserEntityInterface $userEntity): Builder
    {
        return parent::getBuilder($accessToken, $userEntity)
            ->withHeader('kid', Jwt::KEY_ID);
    }
}
