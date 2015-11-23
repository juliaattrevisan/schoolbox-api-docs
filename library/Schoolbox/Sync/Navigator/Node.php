<?php

/**
 * Navigator Node
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Navigator;

class Node {

    /**
     * The name of this breadcrumb
     *
     * @var string
     */
    private $name = '';

    /**
     * The url to which this breadcrumb goes
     *
     * @var string
     */
    private $url = '';

    /**
     * This breacdrumb is currently active
     *
     * @var bool
     */
    private $active = false;

    /**
     * Constructor
     *
     * @param $name
     * @param $url
     * @param $active
     */
    public function __construct($name, $url, $active) {
        $this->setName($name);
        $this->setUrl($url);
        $this->setActive($active);
    }

    /**
     * Is this Node active?
     *
     * @return bool
     */
    public function isActive() {
        return $this->getActive();
    }

    /* Getters/Setters
     */

    /**
     * Set active
     *
     * @param boolean $active
     */
    private function setActive($active)
    {
        $this->active = $active;
    }

    /**
     * Get active
     *
     * @return boolean
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Set name
     *
     * @param string $name
     */
    private function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set url
     *
     * @param string $url
     */
    private function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Get url
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }
}
