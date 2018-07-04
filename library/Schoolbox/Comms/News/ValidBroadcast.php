<?php
/**
 * ValidBroadcast
 *
 * Definition of a valid news article that can be broadcast/viewed by its audience
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
     * Definition of a valid news article that can be viewed by its audience
     *
     * @param Model $article
     * @return bool
     */
    public function isSatisfiedBy(Model $article): bool
    {
        $isValidArticle = (new ValidArticle())->isSatisfiedBy($article);
        $isAccepted = $article->isAccepted() && $article->getAcceptedBy() instanceof User;

        /* Must be valid and have been accepted either by author when moderation isn't required;
         * or accepted by a moderator
         */
        return $isValidArticle && $isAccepted;
    }
}
