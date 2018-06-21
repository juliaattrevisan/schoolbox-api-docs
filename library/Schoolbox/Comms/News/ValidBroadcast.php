<?php
/**
 * ValidBroadcast
 *
 * Definition of a valid news article that can be broadcast to its audience
 * depending on moderation settings and moderation data currently available
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

use Schoolbox\User;

class ValidBroadcast
{

    /**
     * Definition of a valid news article
     *
     * @param Model $article
     * @param bool $moderationRequired
     * @return bool
     */
    public function isSatisfiedBy(Model $article, bool $moderationRequired): bool
    {
        $isValidArticle = (new ValidArticle())->isSatisfiedBy($article);
        $hasModerator = $moderationRequired && $article->getModeratedBy() instanceof User;
        $moderationNotNeeded = !$moderationRequired;

        return $isValidArticle && ($hasModerator || $moderationNotNeeded);
    }
}