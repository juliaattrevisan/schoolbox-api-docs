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
use Schoolbox\API\Token\Jwt;
use Schoolbox\API\Token\JwtParser\Exception\InvalidIdentity;
use Schoolbox\API\Token\JwtParser\Exception\Rejected;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use Schoolbox\UserMapper;

class InstanceRegistryIssued extends Base
{
    public const CLAIM_INSTANCE_ID = 'instance_id';

    public function __construct(
        private UserMapper $userMapper,
        private IConfigLoader $config,
    ) {
    }

    public function accepts(string $jwt): bool
    {
        $claims      = $this->decodeWithoutVerification($jwt);
        $ir_url      = $this->config->get(ConfigMapper::NAME_INSTANCE_REGISTRY_URL);
        $instance_id = $claims[self::CLAIM_INSTANCE_ID] ?? null;
        return isset($claims[self::CLAIM_ISS]) &&
            parse_url($claims[self::CLAIM_ISS], PHP_URL_HOST) === parse_url($ir_url, PHP_URL_HOST) &&
            $instance_id === INSTANCE_ID;
    }

    public function determineUser(string $jwt): User
    {
        $client = new Client();
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
        if (
            $this->decode(
                $jwt,
                JWK::parseKeySet($jwk),
                (int)$this->config->get(ConfigMapper::NAME_JWT_LEEWAY),
                [Jwt::SIGNING_ALGORITHM]
            )
        ) {
            $user = $this->userMapper->findByUsername('alaress');
        } else {
            throw new Rejected('Unable to verify JWT');
        }
        if (!$user) {
            throw new InvalidIdentity('User not found');
        }
        return $user;
    }
}
