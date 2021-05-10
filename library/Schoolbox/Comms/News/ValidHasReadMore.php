<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News;

/**
 * Decide if an article instance should display the customisable Read More
 * link, as defined in admin
 *
 * Depends on multiple aspects of the article:
 *   blurb content
 *   comments
 *   attached files
 *   compact mode
 */
class ValidHasReadMore
{

    /**
     * Decide if $article should show a Read More link, depending on the contents
     * of the article, and whether your configs are set up in a particular way
     *
     * @param Model  $article
     * @param string $readMoreString
     * @param bool   $commentsAllowed
     * @return bool
     */
    public function isSatisfiedBy(Model $article, string $readMoreString, bool $commentsAllowed): bool
    {

        /* If the $readMoreString set in admin var "Read More" Link for Truncated News Articles
         * is empty, it should never display
         */
        if ('' === $readMoreString) {
            return false;
        }

        /* If there are files, there's something to download from the detail screen
         * Show the link
         */
        if ($article->getAttachedFiles()->count()) {
            return true;
        }

        /* If comments enabled, there's something to do on the detail screen
         * Show the link
         */
        if ($article->getCommentsAllowed()) {

            /* But, if the global admin config Enable Comments On News Articles
             * is currently set to Off, you aren't meant to have access to comments
             * anyway, and the entire comment thread will be hidden; nothing to see
             * Hide the link
             *
             * Otherwise your article has comment thread and your instance allows
             * people to witness and interact with its contents
             * Show the link
             */
            return $commentsAllowed;
        }

        /* If the article has a body content, there's something to read
         * It might be the same as the blurb, but there are complications with
         * that comparison. For now, presume there's something to see
         * Show the link
         */
        if ('' !== (string)$article->getBody()) {
            return true;
        }

        /* If the article is a blurb only (the body is empty)
         * There's nothing to see
         * Hide the link
         */

        /* Circumstantially, in a Component context, there's a Compact Mode setting
         * which should also hide the link but it won't actually manifest?
         */

        /* Default case
         * Hide the link
         */
        return false;
    }
}