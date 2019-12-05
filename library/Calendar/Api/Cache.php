<?php
/**
 * Calendar_Api_Cache
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Calendar_Api_Cache extends Alaress_Cache_Abstract {

    /**
     * The url of the stream you just got
     * You can hardcode a url in here, or pass it into init()
     *
     * @var
     */
    protected $url = '';

    /**
     * A prefix for cached filenames
     *
     * @var string
     */
    protected $cacheFilePrefix = 'calendar';

    /**
     * The duration of a cached version before it becomes expired
     * This is a duration measured in seconds
     *
     * @var null
     */
    protected $cachedTime = 3600;

    /**
     * The duration of a lock file before it becomes expired
     * This is a duration measured in seconds
     *
     * @var int
     */
    protected $lockTime = 7200;

    /**
     * By default, $lockTime is a multiple of $cachedTime
     * but you can specify your own after instantiation
     *
     * @var int
     */
    protected $lockTimeMultiplier = 2;

    /**
     * Show plain debugging messages
     *
     * @var bool
     */
    protected $verbose = false;

    /**
     * Hook method
     *
     * @return mixed|void
     */
    protected function setup() {
        $this->cachedTime = Config::get('calendar_api_caching');

        /* By default, set the lockTime to be a multiple of cachedTime
         *
         * You can still override this derivation after instantiation
         * as described above
         */
        $this->lockTime = $this->lockTimeMultiplier * $this->cachedTime;
    }
}
