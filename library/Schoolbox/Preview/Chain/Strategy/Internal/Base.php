<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\Internal;

use Schoolbox\Preview\Chain\Strategy\Base as StrategyBase;

abstract class Base extends StrategyBase {

    /**
     * Url to test
     *
     * @var string
     */
    protected $url = '';

    /**
     * @var string
     */
    protected $hash = '';

    /**
     * Constructor
     *
     * @param $url
     * @param $hash
     */
    public function __construct($url, $hash) {
        $this->setUrl($url);
        $this->setHash($hash);
    }

    /**
     * Check if this is a storage url, it could contain anything
     */
    protected function isStorage() {
        preg_match('#storage\/fetch\.php#', $this->getUrl(), $matches);

        if (count($matches)) {
            return true;
        }

        return false;
    }

    /* Getters/Setters
     */

    /**
     * Set hash
     *
     * @param string $hash
     */
    protected function setHash($hash)
    {
        $this->hash = $hash;
    }

    /**
     * Get hash
     *
     * @return string
     */
    protected function getHash()
    {
        return $this->hash;
    }

    /**
     * Set url
     *
     * @param string $url
     */
    protected function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Get url
     *
     * @return string
     */
    protected function getUrl()
    {
        return $this->url;
    }
}