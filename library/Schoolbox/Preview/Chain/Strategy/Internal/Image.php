<?php

/**
 * Image
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Chain\Strategy\Internal;

class Image extends Base
{

    /**
     * Image files
     *
     * @return mixed
     */
    public function test()
    {
        if ($this->getItem()->getMimetype()->isImage() && $this->getItem()->getMimetype()->getTranscodable()) {
            return [
                'type' => 'image',
                // /storage/image using hash works, as both Storage\Model|File\Model id's won't match, but the hash's do, so use that
                'src' => "/storage/image.php?hash={$this->getItem()->getHash()}"
            ];
        }

        return $this->testNext();
    }
}
