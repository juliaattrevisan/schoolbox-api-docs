<?php
/**
 * Null
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\oEmbed\Strategy;


class Null extends Base {

    /**
     * Base case
     *
     * @return bool
     */
    public function test() {
        return false;
    }
} 