<?php
/**
 * Image
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\Internal;

class Image extends Base {

    /**
     * Image files
     *
     * @return mixed
     */
    public function test() {
        if ($this->getItem()->getMimetype()->isImage()) {
            return [
                'type' => 'image',
                'src' => $this->getUrl()
            ];
        }

        return $this->testNext();
    }
}