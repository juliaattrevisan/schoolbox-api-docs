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
        $claims = $this->decodeWithoutVerification($jwt);

        // Ensure that the JWT was issued by the instance registry
        $this->log->debug(
            sprintf(
                'Checking if JWT contains claim "%s", and that its host matches config "%s"',
                self::CLAIM_ISS,
                ConfigMapper::NAME_INSTANCE_REGISTRY_URL,
            ),
        );
        $irUrl       = $this->config->get(ConfigMapper::NAME_INSTANCE_REGISTRY_URL);
        $claimIssuer = $claims[self::CLAIM_ISS] ?? null;
        if (
            !(
                isset($claimIssuer) &&
                parse_url($claimIssuer, PHP_URL_HOST) === parse_url($irUrl, PHP_URL_HOST)
            )
        ) {
            $this->log->debug(
                sprintf(
                    'JWT claim "%s" hostname does not match config "%s": using next parser',
                    self::CLAIM_ISS,
                    ConfigMapper::NAME_INSTANCE_REGISTRY_URL,
                ),
            );
            return false;
        }

        // Ensure that the JWT's instance ID claim matches this instance's ID
        $this->log->debug(
            sprintf(
                'Checking if JWT contains claim "%s", and that its host matches instance ID',
                self::CLAIM_INSTANCE_ID,
            ),
        );
        $claimInstanceId = $claims[self::CLAIM_INSTANCE_ID] ?? null;
        if ($claimInstanceId !== (string)INSTANCE_ID) {
            $this->log->debug(
                sprintf(
                    'JWT claim "%s" does not match instance ID: using next parser',
                    self::CLAIM_INSTANCE_ID,
                ),
            );
        }

        $this->log->debug(
            sprintf(
                'JWT claims "%s" hostname matches config "%s", and "%s" matches instance ID: using this parser',
                self::CLAIM_ISS,
                ConfigMapper::NAME_INSTANCE_REGISTRY_URL,
                self::CLAIM_INSTANCE_ID,
            ),
        );
        return true;
    }

    /**
     * @inheritdoc
     */
    public function determineUser(#[SensitiveParameter] string $jwt): User
    {
        $client = new Client();
        // Retrieve the JWKs from the instance registry.
        try {
            $this->log->debug(sprintf('Querying instance registry for its JWKS'));
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
        } catch (GuzzleException $e) {
            $message = 'Unable to retrieve instance registry JWKS';
            $this->log->error($message . sprintf(' (message: %s)', $e->getMessage()));
            throw new Exception\Rejected($message, $e->getCode(), $e);
        } catch (\JsonException $e) {
            $message = 'Unable to parse instance registry JWKS';
            $this->log->error($message . sprintf(' (message: %s)', $e->getMessage()));
            throw new Exception\Rejected($message, $e->getCode(), $e);
        }

        // Verify the JWT using the JWKs.
        if (
            $this->decode(
                $jwt,
                JWK::parseKeySet($jwk, self::DEFAULT_SIGNING_ALGORITHM),
                (int)$this->config->get(ConfigMapper::NAME_JWT_LEEWAY),
            )
        ) {
            return $this->findUser(UserMapper::USERNAME_ALARESS_ADMIN, null);
        }

        throw new Rejected('Unable to verify JWT');
    }
}
