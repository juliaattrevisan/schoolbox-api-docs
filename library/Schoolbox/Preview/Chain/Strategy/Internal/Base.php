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
    private $url = '';

    /**
     * @var string
     */
    private $hash = '';

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