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
     * @var string
     */
    private $tokenUrl = 'https://api.plagscan.com/v3/token';

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

        $curl = new \Alaress_Curl_Url($this->getTokenUrl());

        $postfields = array(
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getApiKey()
        );

        $curl->addOptions([
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $postfields,
        ]);

        $curl->exec();

        if (!count($curl->getErrors())) {
            $result = json_decode($curl->getResult());

            if ($result->error) {
                throw new \DomainException($result->error_description);
            }

            return $result->access_token;
        }

        throw new \DomainException('Error generating access token');
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
    private function getTokenUrl()
    {
        return $this->tokenUrl;
    }

    /**
     * @param string $tokenUrl
     * @return $this
     */
    private function setTokenUrl($tokenUrl)
    {
        $this->tokenUrl = $tokenUrl;
        return $this;
    }
}