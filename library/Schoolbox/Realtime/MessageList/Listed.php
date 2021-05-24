<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\MessageList;

class Listed extends AbstractList
{
    /**
     * @return string
     */
    protected function getSuffix()
    {
        return 'listed';
    }
}
