<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Firebase\JWT\JWK;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Schoolbox\API\Token\JwtParser\Exception\InvalidIdentity;
use Schoolbox\API\Token\JwtParser\Exception\Rejected;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use SensitiveParameter;

/**
 * Parser for JWTs issued by the instance registry.
 */
class InstanceRegistryIssued extends Base
{
    /**
     * Key for the instance ID custom JWT claim.
     *
     * This claim is used to identify the Schoolbox instance which issued this
     * JWT, so that its key may be used to decode and verify the JWT.
     */
    public const CLAIM_INSTANCE_ID = 'instance_id';

    /**
     * The signing algorithm to assume that the instance registry uses, if it is
     * not provided in the key.
     */
    private const DEFAULT_SIGNING_ALGORITHM = 'RS256';

    /**
     * @inheritdoc
     */
    public function accepts(#[SensitiveParameter] string $jwt): bool
    {
        $claims      = $this->decodeWithoutVerification($jwt);
        $ir_url      = $this->config->get(ConfigMapper::NAME_INSTANCE_REGISTRY_URL);
        $instance_id = $claims[self::CLAIM_INSTANCE_ID] ?? null;
        // Ensures that the JWT was issued by the instance registry, and that
        // the instance ID custom claim matches the instance ID of this instance.
        return isset($claims[self::CLAIM_ISS]) &&
            parse_url($claims[self::CLAIM_ISS], PHP_URL_HOST) === parse_url($ir_url, PHP_URL_HOST) &&
            $instance_id === (string)INSTANCE_ID;
    }

    /**
     * @inheritdoc
     */
    public function determineUser(#[SensitiveParameter] string $jwt): User
    {
        $client = new Client();
        // Retrieve the JWKs from the instance registry.
        try {
            $jwk = json_decode(
                $client->get(
                    $this->config->get(ConfigMapper::NAME_INSTANCE_REGISTRY_URL) . '/api/jwks'
                )
                       ->getBody()
                       ->getContents(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (GuzzleException | JsonException $e) {
            throw new Rejected(sprintf('Unable to retrieve JWKs from instance registry: %s', $e->getMessage()));
        }
        // Verify the JWT using the JWKs.
        if (
            $this->decode(
                $jwt,
                JWK::parseKeySet($jwk, self::DEFAULT_SIGNING_ALGORITHM),
                (int)$this->config->get(ConfigMapper::NAME_JWT_LEEWAY),
            )
        ) {
            $user = $this->findUser(UserMapper::USERNAME_ALARESS_ADMIN, null);
            if ($user) {
                return $user;
            }
            throw new InvalidIdentity('Schoolbox Admin user does not exist');
        }

        throw new Rejected('Unable to verify JWT');
    }
}
