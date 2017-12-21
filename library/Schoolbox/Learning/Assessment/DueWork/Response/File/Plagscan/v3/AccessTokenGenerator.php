<?php
/**
 * AccessTokenGenerator
 *
 * Generates an Access Token string from a username/api-key combination
 * The Access Token is appended into each request to plagscan api v3.0
 * for authentication purposes
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\v3;

use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Client;

class AccessTokenGenerator
{

    /**
     * @var string
     */
    private $clientId = -1;

    /**
     * @var string
     */
    private $apiKey = '';


    /**
     * Cached value of access token so we only request it once per execution
     * @var string
     */
    private $accessToken;

    /**
     * The time an access token is valid before it is reset
     * @var int
     */
    private $accessTokenExpiry = 3600;

    /**
     * The time the last access token was fetched
     * @var
     */
    private $accessTokenTime;

    /**
     * Api url where stuff is sent
     *
     * @var string
     */
    private $apiUrl = 'https://api.plagscan.com/v3/';

    /**
     * AccessTokenGenerator constructor.
     *
     * @param $clientId
     * @param $apiKey
     */
    public function __construct($clientId, $apiKey)
    {
        $this->setClientId($clientId);
        $this->setApiKey($apiKey);
    }

    /**
     * Generate an Access Token, may use a cached version if one exists that has not expired
     */
    public function generate()
    {
        // Check if the token is cached and return that, otherwise generate a new one and cace
        if (!empty($accessToken = $this->getAccessToken())) {
            return $accessToken;
        }

        $postData = [
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->getClientId(),
            'client_secret' => $this->getApiKey(),
        ];

        $client = new Client([
            "base_uri"    => $this->getApiUrl(),
            "timeout"     => 2.0,
            "http_errors" => false,
        ]);

        try {
            $response = $client->post('token', [
                'form_params' => $postData,
            ]);
        } catch (TransferException $e) {
            throw new \RuntimeException('Error: Plagscan unavailable. Could not generate access token.'.$e->getMessage());
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
     * Set the access token
     *
     * @param $accessToken
     */
    private function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
        $this->accessTokenTime = time();
    }

    /**
     * Gets access token if it has not yet expired
     *
     * @return string
     */
    private function getAccessToken()
    {
        if (!empty($this->accessToken)) {
            if (time() < ($this->accessTokenTime + $this->accessTokenExpiry)) {
                return $this->accessToken;
            }
        }
        return '';
    }

    /**
     * @return string
     */
    private function getClientId()
    {
        return $this->clientId;
    }

    /**
     * @param string $clientId
     * @return $this
     */
    private function setClientId($clientId)
    {
        $this->clientId = $clientId;
        return $this;
    }

    /**
     * @return string
     */
    private function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @param string $apiKey
     * @return $this
     */
    private function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * @return string
     */
    private function getApiUrl()
    {
        return $this->apiUrl;
    }
}
