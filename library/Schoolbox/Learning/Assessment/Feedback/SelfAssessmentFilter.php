<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Feedback;

use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\ISelfAssessableFeedback;

/**
 * Filter for excluding any IFeedbacks which are not self-assessments.
 *
 * Usage:
 * ```
 * $feedbackCollection->filter(new SelfAssessmentFilter());
 * ```
 */
class SelfAssessmentFilter
{
    /**
     * @param IFeedback $feedback
     * @return bool
     */
    public function __invoke(IFeedback $feedback)
    {
        return $feedback instanceof ISelfAssessableFeedback && $feedback->isSelfAssessment();
    }
}
