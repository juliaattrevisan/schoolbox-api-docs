<?php
/**
 * A session class that works around the absence of session_status in PHP 5.3.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Session;
use Aura\Session\Session;

// Define not-yet-defined session status vars
if (!defined('PHP_SESSION_DISABLED')) {
    define('PHP_SESSION_DISABLED', 0);
}
if (!defined('PHP_SESSION_NONE')) {
    define('PHP_SESSION_NONE', 1);
}
if (!defined('PHP_SESSION_ACTIVE')) {
    define('PHP_SESSION_ACTIVE', 2);
}

class Compat53 extends Session {

    /**
     * A phantom session var that is entered into the session using
     * session_register, for the purpose of checking if the session is currently
     * available.
     */
    protected $phantomSessionVar = 'Compat53';

    /**
     * Opens session, and additionally registers $this->phantomSessionVar for
     * later check.
     *
     * @return bool
     */
    public function start() {
        $started = session_start();
        if ($started) {
            // I don't want to raise a deprecated error message, go away
            $errorlevel = error_reporting(error_reporting() & ~E_DEPRECATED);
            session_register($this->phantomSessionVar);
            error_reporting($errorlevel);
        }
        return $started;
    }

    /**
     * Returns the current session status as best I can without session_status().
     *
     * * Will never return `PHP_SESSION_DISABLED`
     * * `PHP_SESSION_NONE` if $this->phantomSessionVar is not registered
     * * `PHP_SESSION_ACTIVE` if $this->phantomSessionVar is registered
     *
     * @return int
     * @see session_status()
     */
    public function getStatus() {
        $errorlevel = error_reporting(error_reporting() & ~E_DEPRECATED);
        $result     = session_is_registered($this->phantomSessionVar);
        error_reporting($errorlevel);
        return $result ? PHP_SESSION_ACTIVE : PHP_SESSION_NONE;
    }
}
