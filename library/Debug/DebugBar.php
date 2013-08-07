<?php
/**
 * A singleton DebugBar instance. Anyone have any better idea on how to do this?
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Debug;

use Config;
use DebugBar\StandardDebugBar;

class DebugBar {

    /**
     * @return \DebugBar\DebugBar
     */
    protected static $debug_bar;

    /**
     * Sets up the debug bar.
     */
    public static function init() {
        self::getInstance();
    }

    /**
     * Retrieves the debug bar.
     *
     * @return \DebugBar\DebugBar
     */
    public static function getInstance() {
        if (!isset(self::$debug_bar)) {
            self::$debug_bar = new StandardDebugBar();
        }
        return self::$debug_bar;
    }

    /**
     * Is the debug bar on or off?
     *
     * @return bool
     */
    public static function isEnabled() {
        return Config::get('debug_mode');
    }

}
