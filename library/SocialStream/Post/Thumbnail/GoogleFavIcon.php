<?php

/**
 * LinkIcon
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Post\Thumbnail;

class GoogleFavIcon extends Thumbnail
{

    public function fetchThumbnail()
    {

        $url = parse_url($this->getUrl());

        if ($url) {
            $thumb = 'http://www.google.com/s2/favicons?domain=' . $url['host'];

            return $thumb;
        }

        if ($this->getNext()) {
            return $this->getNext()->fetchThumbnail();
        }
    }
}
