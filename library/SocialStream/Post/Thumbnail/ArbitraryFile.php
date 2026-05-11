<?php

/**
 * Direct
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Post\Thumbnail;

class ArbitraryFile extends Thumbnail
{
    /**
     * Some kind of resource that is not an image
     * such as a pdf, a docx, etc.
     *
     * @return mixed
     */
    public function fetchThumbnail()
    {

        /* If it has at least the right extension, download it directly
         */
        $path = parse_url($this->getUrl(), PHP_URL_PATH);
        if (!is_string($path)) {
            return '';
        }

        $filename = pathinfo($path, PATHINFO_BASENAME);
        if ('' === $filename) {
            return '';
        }

        try {
            $mapper = new \Schoolbox\File\MimetypeMapper();
            $icon = $mapper->getByFilename($filename)->getIconFile();
        } catch (\Schoolbox\File\Exception $e) {
            return '';
        }

        /* Return a particular formatted string for templating reasons
         */
        return "icon:$icon";
    }
}
