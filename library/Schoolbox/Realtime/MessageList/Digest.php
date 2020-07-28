<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Notification\Instance\Instance;

class Digest extends AbstractList
{
    /**
     * @return string
     */
    protected function getSuffix()
    {
        return 'digest';
    }

    /**
     * @inheritDoc
     */
    public function unset(Instance $instance): void
    {
        // noop
    }
}
