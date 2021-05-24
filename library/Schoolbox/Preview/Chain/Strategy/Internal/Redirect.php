<?php

/**
 * Redirect
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Chain\Strategy\Internal;

class Redirect extends Base
{

    /**
     * Base case
     *
     * @return mixed
     */
    public function test()
    {
        return [
            'type' => 'redirect',
            'src' => $this->getUrl(),
        ];
    }
}
