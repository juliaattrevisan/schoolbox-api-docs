<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Post\Player;

/**
 * Class Chain
 *
 * @package SocialStream\Post\Player
 */
class Chain
{

    /**
     * Resultant thumbnail url
     *
     * @var string
     */
    private $result;


    /**
     * Constructor
     *
     * @param string $url
     */
    public function __construct($url)
    {

        /* Set up chain of responsibility
         */
        $youtube = new Youtube($url);
        $vimeo = new Vimeo($url);
        $ted = new Ted($url);

        /* Try until one of them matches
         */
        $youtube->setNext($vimeo);
        $vimeo->setNext($ted);

        $result = $youtube->fetchThumbnail();

        $this->result = $result;
    }

    /* Getters/Setters
     */

    /**
     * @return string
     */
    public function getResult()
    {
        return $this->result;
    }
}
