<?php

/**
 * Alaress_Saml_Cache
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class Alaress_Saml_Cache extends Alaress_Cache_Abstract
{
    /**
     * A prefix for cached filenames
     *
     * @var string
     */
    protected $cacheFilePrefix = 'saml';

    /**
     * Hook method
     *
     * @return mixed|void
     */
    protected function setup()
    {
        $this->cachedTime = 3600;
        $this->lockTime = $this->lockTimeMultiplier * $this->cachedTime;
    }
}
