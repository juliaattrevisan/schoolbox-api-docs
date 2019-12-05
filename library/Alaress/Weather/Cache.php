<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Alaress_Weather_Cache extends Alaress_Cache_Abstract {

    /**
     * A prefix for cached filenames
     *
     * @var string
     */
    protected $cacheFilePrefix = 'weather';

    /**
     * Hook method
     *
     * @return mixed|void
     */
    protected function setup() {
        $this->cachedTime = Config::get('weather_api_caching');
        $this->lockTime = $this->lockTimeMultiplier * $this->cachedTime;
    }
}
