<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Firebase\JWT\Key;
use Psr\Log\LoggerInterface;
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
     * @param LoggerInterface   $log         Logger
     * @param User\Token\Mapper $tokenMapper Mapper for retrieving user tokens.
     */
    public function __construct(
        UserMapper $userMapper,
        IConfigLoader $config,
        LoggerInterface $log,
        private User\Token\Mapper $tokenMapper,
    ) {
        parent::__construct($userMapper, $config, $log);
    }

    /**
     * @inheritdoc
     */
    public function accepts(#[SensitiveParameter] string $jwt): bool
    {
        $claims = $this->decodeWithoutVerification($jwt);
        $thisInstanceIssuer = $this->determineThisInstanceIssuer();
        $this->log->debug(
            sprintf(
                'Checking if JWT contains claim "%s", and that it matches this instance "%s"',
                self::CLAIM_ISS,
                $thisInstanceIssuer,
            )
        );

        if (!isset($claims[self::CLAIM_ISS])) {
            $this->log->debug(sprintf('JWT does not contain claim "%s": using next parser', self::CLAIM_ISS));
            return false;
        }

        $accepts = $claims[self::CLAIM_ISS] === $this->determineThisInstanceIssuer();
        $this->log->debug(
            sprintf(
                $accepts
                    ? 'JWT claim "%s" value "%s" matches this instance "%s": using this parser'
                    : 'JWT claim "%s" value "%s" does not match this instance "%s": using next parser',
                self::CLAIM_ISS,
                $claims[self::CLAIM_ISS],
                $thisInstanceIssuer,
            )
        );
        return $accepts;
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
            $message = sprintf('JWT must contain a "%s" claim', self::CLAIM_SUB);
            $this->log->error($message);
            throw new Exception\InvalidIdentity($message);
        }
        $username = $claims[self::CLAIM_SUB];
        if (trim($username) === '') {
            $message = sprintf('JWT "%s" claim must not be empty', self::CLAIM_SUB);
            $this->log->error($message);
            throw new Exception\InvalidIdentity($message);
        }
        $user = $this->findUser($claims[self::CLAIM_SUB], self::CLAIM_SUB);
        if (isset($claims[self::CLAIM_JTI])) {
            // if not help instance, validate the jti
            try {
                /** @var User\Token\Model $token */
                $token = $this->tokenMapper->findOneByColumnList(
                    [
                        'user_id' => $user->getId(),
                        'jwt_id' => $claims[self::CLAIM_JTI],
                    ]
                );
                if ($token->getExpiry() && $token->getExpiry()->isPast()) {
                    $message = 'The JWT ID provided has expired';
                    $this->log->error($message);
                    throw new Exception\Rejected($message);
                }
            } catch (MapperException $e) {
                // could not find a token with user id and jwt id
                $message = 'The JWT ID provided does not match a token for the given user';
                $this->log->error($message . sprintf(' (message: %s)', $e->getMessage()));
                throw new Exception\Rejected($message, $e->getCode(), $e);
            }
        }
        if (isset($claims[self::CUSTOM_CLAIM_TASKD]) && $claims[self::CUSTOM_CLAIM_TASKD] === 1) {
            $this->log->info(sprintf('Claim "%s" is set: bypassing IP tracking', self::CUSTOM_CLAIM_TASKD));
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
