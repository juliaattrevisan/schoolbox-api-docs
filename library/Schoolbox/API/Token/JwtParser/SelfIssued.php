<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Firebase\JWT\Key;
use OAT\Library\Lti1p3Core\Message\Payload\MessagePayloadInterface;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Url\Factory;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Parser for JWTs issued by this instance.
 */
class SelfIssued extends Base
{
    /**
     * Constructor
     *
     * @param UserMapper        $userMapper   Mapper for retrieving users once the user in the JWT is identified.
     * @param User\Token\Mapper $tokenMapper  Mapper for retrieving user tokens.
     * @param IConfigLoader     $config       Standard configuration store.
     */
    public function __construct(
        private UserMapper $userMapper,
        private User\Token\Mapper $tokenMapper,
        private IConfigLoader $config,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function accepts(string $jwt): bool
    {
        $claims = $this->decodeWithoutVerification($jwt);
        return isset($claims[MessagePayloadInterface::CLAIM_ISS])
            && $claims[MessagePayloadInterface::CLAIM_ISS] === $this->determineThisInstanceIssuer();
    }

    /**
     * @inheritdoc
     */
    public function determineUser(string $jwt): User
    {
        $claims = $this->decode(
            $jwt,
            new Key($this->config->get(ConfigMapper::NAME_JWT_PUBLIC_KEY), Jwt::SIGNING_ALGORITHM),
            (int)$this->config->get(ConfigMapper::NAME_JWT_LEEWAY),
        );
        if (!isset($claims[MessagePayloadInterface::CLAIM_SUB])) {
            throw new Exception\InvalidIdentity(
                sprintf('JWT must contain a "%s"', MessagePayloadInterface::CLAIM_SUB)
            );
        }
        $username = $claims[MessagePayloadInterface::CLAIM_SUB];
        if (trim($username) === '') {
            throw new Exception\InvalidIdentity(
                sprintf('JWT "%s" claim must not be empty', MessagePayloadInterface::CLAIM_SUB)
            );
        }
        $user   = $this->userMapper->findByUsername($claims[MessagePayloadInterface::CLAIM_SUB]);
        if ($user && isset($claims[MessagePayloadInterface::CLAIM_JTI])) {
            // if issued by schoolbox and jti is provided, validate the jti
            try {
                /** @var User\Token\Model $token */
                $token = $this->tokenMapper->findOneByColumnList(
                    [
                        'user_id' => $user->getId(),
                        'jwt_id'  => $claims[MessagePayloadInterface::CLAIM_JTI],
                    ]
                );
                if ($token->getExpiry() !== null && !$token->getExpiry()->isNull() && $token->getExpiry()->isPast()) {
                    throw new Exception\Rejected('The JWT ID provided has expired');
                }
            } catch (MapperException $e) {
                // could not find a token with user id and jwt id
                throw new Exception\Rejected('The JWT ID provided does not match a token for the given user', 0, $e);
            }
        }
        return $user
            ?? throw new Exception\InvalidIdentity(
                sprintf('JWT "%s" claim does not match a user\'s username', MessagePayloadInterface::CLAIM_SUB)
            );
    }

    /**
     * Determine what this instance's issuer value is.
     *
     * @return string|null
     */
    private function determineThisInstanceIssuer(): ?string
    {
        return (new Factory($this->config))->buildFullyQualified()->getHost();
    }
}
