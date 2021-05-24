<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Post\Player;

/**
 * Class Ted
 *
 * @package SocialStream\Post\Player
 */
class Ted extends Player
{

    /**
     * Fetch thumbnail
     *
     * @return string|null
     */
    public function fetchThumbnail()
    {
        $matches = array();

        preg_match('#www\.ted\.com/talks/([a-zA-Z0-9_]+)\.html#', $this->getUrl(), $matches);

        if (count($matches)) {
            return "http://embed.ted.com/talks/{$matches[1]}.html";
        }

        /* Otherwise get the next class to deal with it
         */
        if ($this->getNext()) {
            return $this->getNext()->fetchThumbnail();
        }

        return null;
    }
}
