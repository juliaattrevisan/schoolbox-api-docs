<?php

/**
 * Redirect
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Chain\Strategy\External;

class Redirect extends Base
{

    /**
     * Base case
     *
     * Just redirect to the location via twig
     *
     * @return array
     */
    public function test()
    {
        return [
            'type' => 'redirect',
            'src' => $this->getUrl(),
        ];
    }
}
