<?php
/**
 * Contains global functions. (I think this is a nicer place than www/include/stdfuncs.php.)
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 * Shim for session_status() method in PHP 5.3.
 *
 * Currently required by Aura\Session library.
 */
if (version_compare(PHP_VERSION, '5.4', '<')) {
    if (!defined('PHP_SESSION_DISABLED')) {
        define('PHP_SESSION_DISABLED', 0);
    }
    if (!defined('PHP_SESSION_NONE')) {
        define('PHP_SESSION_NONE', 1);
    }
    if (!defined('PHP_SESSION_ACTIVE')) {
        define('PHP_SESSION_ACTIVE', 2);
    }
    if (!function_exists('session_status')) {
        /**
         * PHP 5.3 implementation of session_status.
         *
         * Relies on the fact that ini setting 'session.use_trans_sid' cannot be
         * changed when a session is active.
         * @see http://stackoverflow.com/questions/3788369/how-to-tell-if-a-session-is-active/7656468#7656468
         *
         * @return int
         */
        function session_status() {
            $setting = 'session.use_trans_sid';
            $current = ini_get($setting);
            if ($current === false) {
                return PHP_SESSION_DISABLED;
            }

            // ini_set raises a warning when I attempt to change this setting
            // and session is active, I don't want that.
            $errorlevel = error_reporting(0);
            $result     = ini_set($setting, $current);
            error_reporting($errorlevel);

            return $result !== $current ? PHP_SESSION_ACTIVE : PHP_SESSION_NONE;
        }
    }
}
