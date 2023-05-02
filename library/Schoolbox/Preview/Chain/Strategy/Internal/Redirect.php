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
     * @return array|null
     */
    public function test(): ?array
    {
        return [
            'type' => 'redirect',
            'src' => $this->url,
        ];
    }
}
