<?php

/**
 * Session
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Etherpad;

class Session
{

    /**
     * Sessions and cookies are valid for this number of hours
     *
     * @var int
     */
    private $duration = 1;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Generate a validUntil value for Etherpad sessions
     */
    public function generateValidUntil()
    {
        $validUntil = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');
        $validUntil->addHours($this->getDuration());

        return $validUntil;
    }

    /**
     * Generate a cookie for authenticating access to etherpads
     *
     * @param string $sessionId
     */
    public function generateCookie($sessionId)
    {
        setcookie(
            'sessionID',
            $sessionId,
            $_SERVER['REQUEST_TIME'] + (60 * 60 * $this->getDuration()),
            '/'
        );
    }

    /* Getters/Setters
     */

    /**
     * Set duration
     *
     * @param int $duration
     */
    private function setDuration($duration)
    {
        $this->duration = $duration;
    }

    /**
     * Get duration
     *
     * @return int
     */
    private function getDuration()
    {
        return $this->duration;
    }
}
