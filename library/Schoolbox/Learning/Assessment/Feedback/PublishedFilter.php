<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Feedback;

use Schoolbox\Learning\Assessment\IFeedback;

/**
 * Filter for excluding any IFeedbacks which are not published.
 *
 * Usage:
 * ```
 * $feedbackCollection->filter(new PublishedFilter());
 * ```
 */
class PublishedFilter
{
    /**
     * @param IFeedback $feedback
     * @return bool
     */
    public function __invoke(IFeedback $feedback)
    {
        return $feedback->isPublished();
    }
}
