<?php

/**
 * A singleton DebugBar instance. Anyone have any better idea on how to do this?
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\DebugBar;

//use Config;
use DebugBar\StandardDebugBar;

class Instance
{

    /**
     * @return StandardDebugBar
     */
    protected static $debug_bar;

    /**
     * Sets up the debug bar.
     */
    public static function init()
    {
        self::getInstance();
    }

    /**
     * Retrieves the debug bar.
     *
     * @return StandardDebugBar
     */
    public static function getInstance()
    {
        if (!isset(self::$debug_bar)) {
            self::$debug_bar = new StandardDebugBar();
        }
        return self::$debug_bar;
    }

    /**
     * Is the debug bar on or off?
     *
     * Note that this only affects whether database queries are shown in the
     * debug bar, and is currently always false: this is because of the
     * catch-22 situation where:
     * - the debug mode setting is stored in the config DB
     * - queries in debug mar must have a debug mode DB connection, and it's
     *   not possible to tell whether to create one without connecting to the DB
     *
     * @return bool
     */
    public static function isEnabled()
    {
        return false;
//        return Config::get('debug_mode');
    }
}
