<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\InlineGrader;


class Authenticator
{

    /**
     * Registered api token at crocodoc
     * @var string
     */
    protected $apiToken = '';

    /**
     * Constructor
     * @throws \RangeException
     */
    public function __construct()
    {
        if (\Cfg::get('crocodoc_api_token')) {
            $this->setApiToken(\Cfg::get('crocodoc_api_token'));
        }
        else {
            throw new \RangeException('Crocodoc API token is not defined');
        }
    }

    /* Getters/Setters
     */

    /**
     * Set api token
     *
     * @param string $apiToken
     * @return $this
     */
    protected function setApiToken($apiToken)
    {
        $this->apiToken = $apiToken;
        return $this;
    }

    /**
     * Get api token
     *
     * @return string
     */
    public function getApiToken()
    {
        return $this->apiToken;
    }
} 