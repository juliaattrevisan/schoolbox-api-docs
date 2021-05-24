<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Post\Player;

/**
 * Class Thumbnail
 *
 * @package SocialStream\Post\Player
 */
abstract class Player
{

    /**
     * The url to fetch
     *
     * @var string
     */
    protected $url = '';


    /**
     * Next class to deal with the problem
     *
     * @var Player
     */
    protected $next;


    /**
     * Constructor
     *
     * @param string $url
     */
    public function __construct($url)
    {
        $this->setUrl($url);
    }


    /**
     * Subclasses define how to fetch thumbnails
     *
     * @return string|null
     */
    abstract public function fetchThumbnail();


    /* Getters/Setters
     */

    /**
     * Set next
     *
     * @param Player $next
     */
    public function setNext($next)
    {
        $this->next = $next;
    }

    /**
     * Get next
     *
     * @return Player|null
     */
    public function getNext()
    {
        return $this->next;
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
