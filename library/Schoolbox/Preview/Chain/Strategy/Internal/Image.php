<?php

/**
 * Image
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Chain\Strategy\Internal;

use Schoolbox\Resource\File;
use Schoolbox\Resource\Storage;

class Image extends Base
{
    /**
     * Image files
     *
     * @return mixed
     */
    public function test()
    {
        if ($this->getItem()->getMimetype()->isImage()) {
            //set the original url by default, if everything fails, fallback to the original.
            $src = $this->getUrl();
            /**
             * on occasion there are `data-plugin="preview"` urls that are just the default `/send.php` which returns the original file
             * this does not work for HEIC files, as most browsers are unable to preview this file format.
             * So for `data-plugin="preview"` we should return transcoded images.
             *
             * if the user is requesting the hash, they SHOULD HAVE already done the perm checks for the object
             * where if the user is requesting based on the id, we should forward them to cms/image as that accepts an id
             * and will complete permission checks for that object.
             */
            if ($this->getItem() instanceof Storage\Model) {
                $src = "/storage/image.php?hash={$this->getItem()->getHash()}";
            } elseif ($this->getItem() instanceof File\Model) {
                $src = "/cms/image.php?id={$this->getItem()->getId()}&height=2048&width=2048";
            }

            return [
                'type' => 'image',
                'src' => $src
            ];
        }

        return $this->testNext();
    }
}
