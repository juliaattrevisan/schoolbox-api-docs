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
use Schoolbox\Help\InstanceRegistry\Mapper as InstanceRegistryMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Parser for JWTs issued by another instance.
 *
 * It's expected that the other instance's JWT public key be stored in the
 * instance registry mapper's table.
 */
class OtherInstanceIssued extends Base
{
    /**
     * Key for the instance ID custom JWT claim.
     *
     * This claim is used to identify the Schoolbox instance which issued this
     * JWT, so that its key may be used to decode and verify the JWT.
     */
    public const CLAIM_INSTANCE_ID = self::CUSTOM_CLAIM_NAMESPACE . 'instance_id';

    /**
     * Constructor
     *
     * @param UserMapper             $userMapper             Mapper for retrieving users once the user in the JWT is
     *                                                       identified.
     * @param InstanceRegistryMapper $instanceRegistryMapper Mapper for retrieving an instance's JWT public key.
     * @param IConfigLoader          $config                 Standard configuration store.
     */
    public function __construct(
        private UserMapper $userMapper,
        private InstanceRegistryMapper $instanceRegistryMapper,
        private IConfigLoader $config,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function accepts(string $jwt): bool
    {
        $claims = $this->decodeWithoutVerification($jwt);
        return isset($claims[self::CLAIM_INSTANCE_ID]);
    }

    /**
     * @inheritdoc
     */
    public function determineUser(string $jwt): User
    {
        // Look up the instance in the registry, and get its public key so that
        // the JWT can be verified.
        $unverifiedClaims = $this->decodeWithoutVerification($jwt);
        $instanceId       = $unverifiedClaims[self::CLAIM_INSTANCE_ID];
        try {
            $instanceDetails = $this->instanceRegistryMapper->findByInstanceId($instanceId);
        } catch (MapperException $e) {
            throw new Exception\Rejected(sprintf('Instance "%s" not registered', $instanceId), 0, $e);
        }

        // Verify the payload
        $claims = $this->decode(
            $jwt,
            new Key($instanceDetails->getJwtPublicKey(), Jwt::SIGNING_ALGORITHM),
            (int)$this->config->get(ConfigMapper::NAME_JWT_LEEWAY),
        );

        // Now the JWT is decoded and verified:
        // create a session for the user matching the email address in the JWT
        $email = $claims[self::CLAIM_EMAIL] ?? null;
        if (!isset($email)) {
            throw new Exception\InvalidIdentity(sprintf('JWT must contain an "%s" claim', self::CLAIM_EMAIL));
        }

        // The email must be valid too, or at least not blank
        $email = (string)$email;
        if (trim($email) === '') {
            throw new Exception\InvalidIdentity(sprintf('JWT "%s" claim must not be empty', self::CLAIM_EMAIL));
        }
        $user = $this->userMapper->findByUsername($email);
        if (!$user) {
            throw new Exception\InvalidIdentity(sprintf('JWT "%s" claim does not match a user', self::CLAIM_EMAIL));
        }
        if (!$user->getEnabled()) {
            throw new Exception\InvalidIdentity(
                sprintf('JWT "%s" claim matches a deactivated user', self::CLAIM_EMAIL)
            );
        }
        return $user;
    }
}
