<?php
/**
 * ValidArticleIsPubliclyReadable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

use Schoolbox\User;

class ValidArticleIsPubliclyReadable
{

    /**
     * This prevents articles in draft/moderation phases from being seen.
     *
     * If an article is published or expired and in the archive, and you're in its audience, you can
     * see its contents.
     *
     * If you aren't in the audience and the article is shareable,
     * then you can see its contents.
     *
     * Otherwise, you can't see it.
     *
     * @param User  $user
     * @param Model $article
     * @return bool
     */
    public function isSatisfiedBy(User $user, Model $article): bool
    {
        $isPublished = $article->isPublished();
        $isExpired = $article->isExpired();
        $isShareable = $article->isShareable();

        /* Only published/expired articles can be publicly seen
         */
        if ($isPublished || $isExpired) {

            /* If you are in its audience, you can see it
             */

            /* If it's shareable, you can see it
             */
            if ($isShareable) {
                return true;
            }
        }

        return false;
    }
}