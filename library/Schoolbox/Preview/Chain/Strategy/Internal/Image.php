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
        if ($this->getItem()->getMimetype()->isImage()) {
            return [
                'type' => 'image',
                'src' => $this->getUrl()
            ];
        }

        return $this->testNext();
    }
}
