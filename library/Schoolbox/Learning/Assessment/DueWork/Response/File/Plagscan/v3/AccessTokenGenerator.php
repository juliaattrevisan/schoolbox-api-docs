<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\v3;

use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Client;

/**
 * Class AccessTokenGenerator
 *
 *
 * Generates an Access Token string from a username/api-key combination
 * The Access Token is appended into each request to plagscan api v3.0
 * for authentication purposes
 *
 * @package Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\v3
 */
class AccessTokenGenerator
{

    /**
     * @var string
     */
    private $clientId;


    /**
     * @var string
     */
    private $apiKey;


    /**
     * Cached value of access token so we only request it once per execution
     *
     * @var string
     */
    private $accessToken;


    /**
     * The time an access token is valid before it is reset
     *
     * @var int
     */
    private $accessTokenExpiry = 3600;


    /**
     * The time the last access token was fetched
     *
     * @var int
     */
    private $accessTokenTime;


    /**
     * @var Client
     */
    private $client;


    /**
     * AccessTokenGenerator constructor.
     *
     * @param Client $client
     * @param string $clientId
     * @param string $apiKey
     */
    public function __construct($client, $clientId, $apiKey)
    {
        $this->clientId = $clientId;
        $this->apiKey = $apiKey;
        $this->client = $client;
    }


    /**
     * Generate an Access Token, may use a cached version if one exists that has not expired
     * @return string
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function generate(): string
    {
        // Check if the token is cached and return that, otherwise generate a new one and cace
        if (!empty($accessToken = $this->getAccessToken())) {
            return $accessToken;
        }

        $postData = [
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->clientId,
            'client_secret' => $this->apiKey,
        ];

        try {
            $response = $this->client->post('token', [
                'form_params' => $postData,
            ]);
        } catch (TransferException $e) {
            throw new \RuntimeException('Error: Plagscan unavailable. Could not generate access token.' . $e->getMessage());
        }

        /* What was the outcome?
         */
        $content = json_decode($response->getBody());

        if ($response->getStatusCode() == 200) {
            $this->setAccessToken($content->access_token);
            return $content->access_token;
        }

        /* If you ended up here, the document can't be transmitted for various reasons
         * so just shove them out for logging purposes
         */
        throw new \DomainException("Error: {$content->error->code} - {$content->error->message}");
    }

    /* Getters/Setters
     */

    /**
     * Gets access token if it has not yet expired
     *
     * @return string
     */
    private function getAccessToken(): string
    {
        if (!empty($this->accessToken) && time() < ($this->accessTokenTime + $this->accessTokenExpiry)) {
            return $this->accessToken;
        }

        return '';
    }

    /**
     * Set the access token
     *
     * @param string $accessToken
     */
    private function setAccessToken($accessToken): void
    {
        $this->accessToken = $accessToken;
        $this->accessTokenTime = time();
    }
}
