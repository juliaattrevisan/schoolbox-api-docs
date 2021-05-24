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
use Schoolbox\User;

interface SourceInterface
{

    public const HARD_LIMIT = 50;

    /**
     * Generate n Instances, from the perspective of a particular user.
     *
     * @param int  $context - most likely a category ID or ignored
     * @param User $user    - the user for whom this feed is being retrieved
     * @param int  $limit   - how many messages to retrieve
     * @param int  $offset  - by default, most recent
     * @return Collection|Instance[]
     */
    public function getFeed($context, User $user, $limit = self::HARD_LIMIT, $offset = 0);
}
