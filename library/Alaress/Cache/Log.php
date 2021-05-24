<?php

/**
 * Alaress_Cache_Locker_Log
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Alaress_Cache_Log
{

    /**
     * Show plain debugging messages
     *
     * @var bool
     */
    private $verbose = false;

    /**
     * Constructor
     *
     * @param bool $verbosity
     */
    public function __construct($verbosity)
    {
        $this->setVerbose($verbosity);
    }

    /**
     * Logging mechanism
     *
     * @param string $text
     */
    public function report($text)
    {
        if ($this->getVerbose()) {
            echo $text . "<BR>\n";
        }
    }

    /* Getters/Setters
     */

    /**
     * Set verbose
     *
     * @param bool $verbose
     * @return self
     */
    private function setVerbose($verbose)
    {
        $this->verbose = $verbose;

        return $this;
    }

    /**
     * Get verbose
     *
     * @return bool
     */
    private function getVerbose()
    {
        return $this->verbose;
    }
}
