<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Firebase\JWT as JwtLibrary;
use OAT\Library\Lti1p3Core\Message\Payload\MessagePayloadInterface;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Url\Factory;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Parser for JWTs issued by this instance.
 */
class SelfIssued implements IParser
{
    /**
     * Constructor
     *
     * @param Jwt               $tokenBuilder The token builder, used for decoding JWTs.
     * @param UserMapper        $userMapper   Mapper for retrieving users once the user in the JWT is identified.
     * @param User\Token\Mapper $tokenMapper  Mapper for retrieving user tokens.
     * @param IConfigLoader     $config       Standard configuration store.
     */
    public function __construct(
        private Jwt $tokenBuilder,
        private UserMapper $userMapper,
        private User\Token\Mapper $tokenMapper,
        private IConfigLoader $config,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function accepts(array $claims): bool
    {
        return isset($claims[MessagePayloadInterface::CLAIM_ISS])
            && $claims[MessagePayloadInterface::CLAIM_ISS] === $this->determineThisInstanceIssuer();
    }

    /**
     * @inheritdoc
     */
    public function determineUser(string $jwt, array $claims): User
    {
        $claims = $this->tokenBuilder->decode($jwt);
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
                    throw new JwtLibrary\ExpiredException();
                }
            } catch (MapperException $e) {
                // could not find a token with user id and jwt id
                throw new JwtLibrary\SignatureInvalidException($e->getMessage(), 0, $e);
            }
        }
        return $user ?? throw new SchoolboxNotFoundException();
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
