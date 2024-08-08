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
     * @inheritdoc
     */
    public function accepts(#[SensitiveParameter] string $jwt): bool
    {
        $claims = $this->decodeWithoutVerification($jwt);
        return isset($claims[self::CLAIM_INSTANCE_ID]);
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
        } catch (GuzzleException | \JsonException $e) {
            throw new Exception\Rejected(
                sprintf('Unable to retrieve instance details for instance ID "%s"', $instanceId),
            );
        }

        $keySet = JWK::parseKeySet($jwks, Jwt::SIGNING_ALGORITHM);
        if (!isset($headers['kid'])) {
            if (count($keySet) !== 1) {
                throw new Exception\Rejected(
                    sprintf('Instance "%s" has %d keys in its JWKS, but the JWT does not specify a "kid" claim', $instanceId, count($keySet)),
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
        $user = $this->findUser($email, self::CLAIM_EMAIL);
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
