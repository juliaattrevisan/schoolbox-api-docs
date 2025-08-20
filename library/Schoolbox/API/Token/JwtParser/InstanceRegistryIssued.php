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
        $claims = $this->decodeWithoutVerification($jwt);

        // Ensure that the JWT was issued by the instance registry
        $irUrl       = $this->config->get(ConfigMapper::NAME_INSTANCE_REGISTRY_URL);
        $claimIssuer = $claims[self::CLAIM_ISS] ?? null;
        $this->log->debug(
            sprintf(
                'Checking JWT %s "%s" against config %s "%s" (hostname must match)',
                self::CLAIM_ISS,
                $claimIssuer,
                ConfigMapper::NAME_INSTANCE_REGISTRY_URL,
                $irUrl,
            )
        );
        if (
            !(
                isset($claimIssuer) &&
                parse_url($claimIssuer, PHP_URL_HOST) === parse_url($irUrl, PHP_URL_HOST)
            )
        ) {
            return false;
        }

        // Ensure that the JWT's instance ID claim matches this instance's ID
        $claimInstanceId = $claims[self::CLAIM_INSTANCE_ID] ?? null;
        $this->log->debug(
            sprintf(
                'Checking JWT %s "%s" against instance ID %s',
                self::CLAIM_INSTANCE_ID,
                $claimInstanceId,
                INSTANCE_ID,
            )
        );
        return $claimInstanceId === (string)INSTANCE_ID;
    }

    /**
     * @inheritdoc
     */
    public function determineUser(#[SensitiveParameter] string $jwt): User
    {
        $client = new Client();
        // Retrieve the JWKs from the instance registry.
        try {
            $jwks = json_decode(
                $client->get(
                    $this->config->get(ConfigMapper::NAME_INSTANCE_REGISTRY_URL) . '/api/jwks'
                )
                       ->getBody()
                       ->getContents(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (GuzzleException $e) {
            throw new Exception\Rejected('Unable to retrieve instance registry JWKS', $e->getCode(), $e);
        } catch (\JsonException $e) {
            throw new Exception\Rejected('Unable to parse instance registry JWKS', $e->getCode(), $e);
        }

        // Verify the JWT using the JWKS,
        // then find Schoolbox Admin and log them in.
        $this->decode(
            $jwt,
            JWK::parseKeySet($jwks, self::DEFAULT_SIGNING_ALGORITHM),
            (int)$this->config->get(ConfigMapper::NAME_JWT_LEEWAY),
        );
        return $this->findUser(UserMapper::USERNAME_ALARESS_ADMIN, null);
    }
}
