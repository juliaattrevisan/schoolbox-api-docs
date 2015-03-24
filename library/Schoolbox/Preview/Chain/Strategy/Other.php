<?php
/**
 * Other
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy;


class Other extends Base {

    /**
     * Base case
     */
    public function test() {
        return 'Is some kind of generic file';
    }
}