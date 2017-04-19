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

namespace Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\v3;
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
     * Generate an Access Token
     */
    public function generate()
    {

        /* TODO: Cache plagscanAccessToken here. If it is about to expire, regenerate; otherwise reuse
         * At the moment access token is respawned every time which isn't always necessary. To respawn
         * an access token, a curl request must be sent to plag
         */

        $postData = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getApiKey()
        ];

        $client = new Client([
            "base_uri" => $this->getApiUrl(),
            "timeout" => 2.0,
            "http_errors" => false
        ]);

        try {
            $response = $client->post('token', [
                'form_params' => $postData
            ]);
        }
        catch (TransferException $e) {
            throw new \DomainException('Error: Plagscan unavailable. Could not generate access token.');
        }

        /* What was the outcome?
         */
        $content = json_decode($response->getBody());

        if ($response->getStatusCode() == 200) {
            return $content->access_token;
        }

        /* If you ended up here, the document can't be transmitted for various reasons
         * so just shove them out for logging purposes
         */
        throw new \DomainException("Error: {$content->error} - {$content->error_description}");
    }

    /* Getters/Setters
     */

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

    /**
     * @param string $apiUrl
     * @return $this
     */
    private function setApiUrl($apiUrl)
    {
        $this->apiUrl = $apiUrl;
        return $this;
    }
}