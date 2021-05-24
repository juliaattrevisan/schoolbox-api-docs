<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Feed;

use Alaress\Async\Notification\Instance\Instance;
use Schoolbox\Collection;

interface FeedableMapperInterface
{

    /**
     * @param int $context
     * @param int $limit
     * @param int $offset
     * @param int $time
     * @return Collection|Instance[]
     */
    public function findForFeed($context, $limit, $offset = 0, $time = 0);
}
