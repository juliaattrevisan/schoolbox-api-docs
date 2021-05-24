<?php

/**
 * Direct
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Post\Thumbnail;

class Direct extends Thumbnail
{

    /**
     * Find itemprop='image'
     *
     * @return mixed
     */
    public function fetchThumbnail()
    {
        if (self::isImage($this->getUrl()) && preg_match('#^http#', $this->getUrl())) {
            return $this->getUrl();
        }

        /* Otherwise get the next class to deal with it
         */
        if ($this->getNext()) {
            return $this->getNext()->fetchThumbnail();
        }
        return '';
    }
}
