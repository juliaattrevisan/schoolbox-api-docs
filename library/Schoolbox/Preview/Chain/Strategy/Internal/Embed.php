<?php
/**
 * Embed
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\Internal;


class Embed extends Base {

    /**
     * Test
     *
     * @return mixed
     */
    public function test() {
        return $this->testNext();
    }
} 