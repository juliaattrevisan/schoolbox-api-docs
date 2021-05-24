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
        $mapper = new \Schoolbox\File\MimetypeMapper();

        try {
            $icon = $mapper->getByFilename($this->getUrl())->getIconFile();
        } catch (\Schoolbox\File\Exception $e) {
            return '';
        }

        /* Return a particular formatted string for templating reasons
         */
        return "icon:$icon";
    }
}
