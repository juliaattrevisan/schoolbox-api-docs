<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Firebase\JWT\Key;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Url\Factory;
use Schoolbox\User;
use Schoolbox\UserMapper;
use SensitiveParameter;

/**
 * Parser for JWTs issued by this instance.
 */
class SelfIssued extends Base
{
    public const CUSTOM_CLAIM_TASKD = self::CUSTOM_CLAIM_NAMESPACE . 'taskd';

    /**
     * Constructor
     *
     * @param UserMapper        $userMapper  Mapper for retrieving users once the user in the JWT is identified.
     * @param IConfigLoader     $config      Standard configuration store.
     * @param User\Token\Mapper $tokenMapper Mapper for retrieving user tokens.
     */
    public function __construct(
        UserMapper $userMapper,
        IConfigLoader $config,
        private User\Token\Mapper $tokenMapper,
    ) {
        parent::__construct($userMapper, $config);
    }

    /**
     * @inheritdoc
     */
    public function accepts(#[SensitiveParameter] string $jwt): bool
    {
        $claims = $this->decodeWithoutVerification($jwt);
        return isset($claims[self::CLAIM_ISS]) && $claims[self::CLAIM_ISS] === $this->determineThisInstanceIssuer();
    }

    /**
     * @inheritdoc
     */
    public function determineUser(#[SensitiveParameter] string $jwt): User
    {
        $claims = $this->decode(
            $jwt,
            new Key($this->config->get(ConfigMapper::NAME_JWT_PUBLIC_KEY), Jwt::SIGNING_ALGORITHM),
            (int)$this->config->get(ConfigMapper::NAME_JWT_LEEWAY),
        );
        if (!isset($claims[self::CLAIM_SUB])) {
            throw new Exception\InvalidIdentity(
                sprintf('JWT must contain a "%s"', self::CLAIM_SUB)
            );
        }
        $username = $claims[self::CLAIM_SUB];
        if (trim($username) === '') {
            throw new Exception\InvalidIdentity(
                sprintf('JWT "%s" claim must not be empty', self::CLAIM_SUB)
            );
        }
        $user = $this->findUser($claims[self::CLAIM_SUB], self::CLAIM_SUB);
        if (isset($claims[self::CLAIM_JTI])) {
            // if issued by schoolbox and jti is provided, validate the jti
            try {
                /** @var User\Token\Model $token */
                $token = $this->tokenMapper->findOneByColumnList(
                    [
                        'user_id' => $user->getId(),
                        'jwt_id'  => $claims[self::CLAIM_JTI],
                    ]
                );
                if ($token->getExpiry() && $token->getExpiry()->isPast()) {
                    throw new Exception\Rejected('The JWT ID provided has expired');
                }
            } catch (MapperException $e) {
                // could not find a token with user id and jwt id
                throw new Exception\Rejected('The JWT ID provided does not match a token for the given user', 0, $e);
            }
        }
        if (isset($claims[self::CUSTOM_CLAIM_TASKD]) && $claims[self::CUSTOM_CLAIM_TASKD] === 1) {
            $this->bypassIpTracking = true;
        }

        return $user;
    }

    /**
     * Determine what this instance's issuer value is.
     *
     * @return string
     */
    private function determineThisInstanceIssuer(): string
    {
        return (new Factory($this->config))->buildFullyQualified()->getHost();
    }
}
