<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Post\Player;

/**
 * Class Youtube
 *
 * @package SocialStream\Post\Player
 */
class Youtube extends Player
{

    /**
     * Fetch thumbnail
     *
     * @return string|null
     */
    public function fetchThumbnail()
    {
        $matches = array();

        preg_match('/v=([a-zA-Z0-9_\-]+)&?/', $this->getUrl(), $matches);

        if (count($matches)) {
            return "//www.youtube.com/embed/{$matches[1]}";
        }

        /* Otherwise get the next class to deal with it
         */
        if ($this->getNext()) {
            return $this->getNext()->fetchThumbnail();
        }

        return null;
    }
}
