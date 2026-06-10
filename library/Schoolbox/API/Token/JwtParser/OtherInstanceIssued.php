<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Firebase\JWT\JWK;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\User;
use SensitiveParameter;

/**
 * Parser for JWTs issued by another instance.
 *
 * It's expected that the other instance's JWKS URL will be provided by the
 * instance registry.
 *
 * These JWTs are usually issued by following a school's "Visit Help" link: the
 * JWT will be accepted by the Help instance.
 */
class OtherInstanceIssued extends InstanceIssued
{
    /**
     * Key for the instance ID custom JWT claim.
     *
     * This claim is used to identify the Schoolbox instance which issued this
     * JWT, so that its key may be used to decode and verify the JWT.
     */
    public const CLAIM_INSTANCE_ID = self::CUSTOM_CLAIM_NAMESPACE . 'instance_id';

    /**
     * @inheritdoc
     */
    public function accepts(#[SensitiveParameter] string $jwt): bool
    {
        $claims     = $this->decodeWithoutVerification($jwt);
        $instanceId = $claims[self::CLAIM_INSTANCE_ID] ?? null;
        $this->log->debug(sprintf('Checking JWT %s "%s" is set', self::CLAIM_INSTANCE_ID, $instanceId));
        return isset($instanceId);
    }

    /**
     * @inheritdoc
     */
    public function determineUser(#[SensitiveParameter] string $jwt): User
    {
        // Look up the instance in the registry, and get its public key so that
        // the JWT can be verified.
        $unverifiedClaims = $this->decodeWithoutVerification($jwt);
        $headers          = $this->decodeHeaders($jwt);
        $instanceId       = $unverifiedClaims[self::CLAIM_INSTANCE_ID];
        $client           = new Client();
        try {
            $response = json_decode(
                $client->get(
                    $this->config->get(ConfigMapper::NAME_INSTANCE_REGISTRY_URL) . '/api/instances/' . $instanceId . "/jwks?toInstance=" . INSTANCE_ID
                )
                       ->getBody()
                       ->getContents(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            $jwks = json_decode($client->get($response['url'])->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (GuzzleException $e) {
            throw new Exception\Rejected(
                sprintf('Unable to retrieve instance details for instance "%s"', $instanceId),
                $e->getCode(),
                $e,
            );
        } catch (\JsonException $e) {
            throw new Exception\Rejected(
                sprintf('Unable to parse instance details for instance "%s"', $instanceId),
                $e->getCode(),
                $e,
            );
        }

        $keySet = JWK::parseKeySet($jwks, Jwt::SIGNING_ALGORITHM);
        if (!isset($headers['kid'])) {
            if (count($keySet) !== 1) {
                throw new Exception\Rejected(
                    sprintf('Instance has %d keys in its JWKS, but the JWT does not specify a "kid" claim', count($keySet)),
                );
            }
            $keySet = $keySet[Jwt::KEY_ID];
        }

        // Verify the payload
        $claims = $this->decode(
            $jwt,
            $keySet,
            (int)$this->config->get(ConfigMapper::NAME_JWT_LEEWAY),
        );

        // Validate audience
        $this->assertValidAudience($claims);

        // Now the JWT is decoded and verified:
        // create a session for the user matching the email address in the JWT.
        // Note that unlike the self-issued JWT, the JTI cannot be verified.
        $email = $this->extractUsername($claims, self::CLAIM_EMAIL);
        return $this->findUser($email, self::CLAIM_EMAIL);
    }
}
