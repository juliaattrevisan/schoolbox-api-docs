<?php
/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Schoolbox\API\Token\Jwt;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Help\InstanceRegistry\Mapper as InstanceRegistryMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;

/**
 * Parser for JWTs issued by another instance.
 *
 * It's expected that the other instance's JWT public key be stored in the
 * instance registry mapper's table.
 */
class OtherInstanceIssued implements IParser
{
    /**
     * Key for the email JWT claim. This is a public claim, so it doesn't need
     * a prefix.
     */
    private const CLAIM_EMAIL = 'email';

    /**
     * Key for the instance ID custom JWT claim.
     */
    private const CLAIM_INSTANCE_ID = Jwt::CUSTOM_CLAIM_NAMESPACE . 'instance_id';

    /**
     * Constructor
     *
     * @param Jwt                    $tokenBuilder           The token builder, used for decoding JWTs.
     * @param UserMapper             $userMapper             Mapper for retrieving users once the user in the JWT is
     *                                                       identified.
     * @param InstanceRegistryMapper $instanceRegistryMapper Mapper for retrieving an instance's JWT public key.
     */
    public function __construct(
        private Jwt $tokenBuilder,
        private UserMapper $userMapper,
        private InstanceRegistryMapper $instanceRegistryMapper,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function accepts(array $claims): bool
    {
        return isset($claims[self::CLAIM_INSTANCE_ID]);
    }

    /**
     * @inheritdoc
     */
    public function determineUser(string $jwt, array $claims): ?User
    {
        // Look up the instance in the registry, and get its public key so that
        // the JWT can be verified.
        $instanceId = $claims[self::CLAIM_INSTANCE_ID];
        try {
            $instanceDetails = $this->instanceRegistryMapper->findByInstanceId($instanceId);
        } catch (MapperException $e) {
            throw new UnexpectedValueException(sprintf('Instance "%s" not registered', $instanceId), 0, $e);
        }

        // Verify the payload
        $verifiedPayload = $this->tokenBuilder->decode($jwt, $instanceDetails->getJwtPublicKey());

        // Now the JWT is decoded and verified:
        // create a session for the user matching the email address in the JWT
        $email = $verifiedPayload[self::CLAIM_EMAIL] ?? null;
        if (!isset($email)) {
            throw new UnexpectedValueException(sprintf('JWT must contain an "%s" claim', self::CLAIM_EMAIL));
        }

        // The email must be valid too, or at least not blank
        $email = (string)$email;
        if (trim($email) === '') {
            throw new UnexpectedValueException(sprintf('JWT "%s" claim must not be empty', self::CLAIM_EMAIL));
        }
        return $this->userMapper->findByUsername($email) ?? throw new SchoolboxNotFoundException();
    }
}
