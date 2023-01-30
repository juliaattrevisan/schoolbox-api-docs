<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\AccessToken;

/**
 * Class enerator
 *
 * Generate a Veracross Access Token
 *
 * This is required for all endpoint requests and naturally expire after
 * 3600 seconds have elapsed. This lifetime is decided by Veracross, not
 * us, and we can't change its duration.
 *
 * If a token is about to expire, regenerate a new one and return that
 * rather than risk it expiring mid-usage
 */
class Generator
{
    /**
     * Lifetime of a token, as set by Veracross
     */
    private const TOKEN_DURATION = 3600;


    /**
     * @var null|AccessToken
     */
    private ?AccessToken $accessToken = null;


    /**
     * Constructor.
     *
     * @param string $schoolRoute
     * @param string $clientId
     * @param string $clientSecret
     */
    public function __construct(
        private string $schoolRoute,
        private string $clientId,
        private string $clientSecret
    ) {
    }


    /**
     * Generate a token if we need a new one
     *    - when we're creating the first token
     *    - when a previously-created token is about to expire
     *
     * @return AccessToken
     */
    public function generate(): AccessToken
    {
        if (null === $this->accessToken) {
            /* If we have never spawned a token; this is our first time use
             * Spawn a new one
             */
            $this->accessToken = $this->create();
        } elseif ($this->accessToken->isExpired()) {
            /* The token we previously created is about to expire
             * Spawn a new one
             */
            $this->accessToken = $this->create();
        }

        /* The one we have is currently alright; reuse it
         */
        return $this->accessToken;
    }


    /**
     * Create a new AccessToken instance which knows the datetime
     * of its own death so we can easily decide if it's expired
     *
     * Subtract 60 seconds so we have enough time to
     * respawn a new access token
     */
    private function create(): AccessToken
    {
        /* Guzzle the Access Token Generation endpoint
         */

        /* Get the value of that new token
         */

        /* Predict the death of the token and subtract an arbitrary
         * amount off its actual lifespan to give us time to spawn another
         */
        $expiryTime = time() + self::TOKEN_DURATION - 60;

        /* Spawn the token
         */
        return new AccessToken($expiryTime, $token);
    }
}
