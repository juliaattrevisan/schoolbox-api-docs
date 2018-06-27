<?php
/**
 * ValidCanAccept
 *
 * Definition of an article that can be accepted during moderation process
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Moderation;

use Schoolbox\Comms\News\Model;

class ValidCanAccept
{

    /**
     * Definition of an article that can be accepted during moderation process
     *
     * @param bool  $moderationRequired
     * @param Model $news
     * @return bool
     */
    public function isSatisfiedBy(bool $moderationRequired, Model $news): bool
    {
        return $moderationRequired && !$news->getSubmittedAt()->isNull() && $news->getModeratedAt()->isNull();
    }
}
