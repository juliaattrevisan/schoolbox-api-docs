<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Navigator;

/**
 * Class Node
 * @package Schoolbox\Sync\Navigator
 */
class Node
{

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
     * @param string $name
     * @param string $url
     * @param bool $active
     */
    public function __construct($name, $url, $active)
    {
        $this->setName($name);
        $this->setUrl($url);
        $this->setActive($active);
    }

    /**
     * Is this Node active?
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->getActive();
    }

    /* Getters/Setters
     */

    /**
     * Set active
     *
     * @param bool $active
     */
    private function setActive($active)
    {
        $this->active = $active;
    }

    /**
     * Get active
     *
     * @return bool
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
