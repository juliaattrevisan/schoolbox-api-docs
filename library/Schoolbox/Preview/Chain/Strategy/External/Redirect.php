<?php
/**
 * Redirect
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\External;


class Redirect extends Base {

    /**
     * Base case
     *
     * Just redirect to the location via twig
     */
    public function test() {
        return [
            'type' => 'redirect',
            'src' => $this->getUrl(),
        ];
    }
} 