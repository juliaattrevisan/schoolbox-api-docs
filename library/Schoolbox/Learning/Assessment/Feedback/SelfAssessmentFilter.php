<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Feedback;

use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\ISelfAssessableFeedback;

class SelfAssessmentFilter
{
    public function __invoke(IFeedback $feedback)
    {
        return $feedback instanceof ISelfAssessableFeedback && $feedback->isSelfAssessment();
    }
}
