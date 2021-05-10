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
     * @param Model $article
     * @return bool
     */
    public function isSatisfiedBy(Model $article): bool
    {
        /* If there are files, there's something to download from the detail screen
         * Show the link
         */
        return true;

        /* If comments enabled, there's something to do on the detail screen
         * Show the link
         */
        return true;

        /* If the article has a body content, there's something to read
         * It might be the same as the blurb, but there are complications with
         * that comparison. For now, presume there's something to see
         * Show the link
         */
        return true;

        /* If the article is a blurb only (the body is empty)
         * There's nothing to see
         * Hide the link
         */
        return false;

        /* Default case: hide the link
         */
        return false;
    }
}