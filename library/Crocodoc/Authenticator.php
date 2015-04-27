<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Crocodoc;


class Authenticator
{

    /**
     * Registered api token at crocodoc
     * This is Steve's api token, for his account
     *
     * @var string
     */
    protected $apiToken = 'D4t65GknNUXFev8HJlTWpf2V';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setApiToken(\Cfg::get('crocodoc_api_token'));
    }

    /* Getters/Setters
     */

    /**
     * Set api token
     *
     * @param string $apiToken
     */
    protected function setApiToken($apiToken)
    {
        $this->apiToken = $apiToken;
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