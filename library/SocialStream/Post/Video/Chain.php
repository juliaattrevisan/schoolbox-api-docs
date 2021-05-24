<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Post\Video;

/**
 * Class Chain
 *
 * @package SocialStream\Post\Video
 */
class Chain
{

    /**
     * Resultant thumbnail poster and source
     *
     * @var array
     */
    private $result = array();


    /**
     * Constructor
     *
     * @param string $url
     */
    public function __construct($url)
    {

        /* Set up chain of responsibility
         */
        $storage = new Storage($url);
        $storage->setNext(new Resource($url));
        $result = $storage->fetchThumbnail();
        $this->setResult($result);
    }

    /* Getters/Setters
     */

    /**
     * Set result
     *
     * @param array $result
     */
    private function setResult($result)
    {
        $this->result = $result;
    }

    /**
     * @return array
     */
    public function getResult()
    {
        return $this->result;
    }
}
