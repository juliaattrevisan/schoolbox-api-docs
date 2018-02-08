<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment;

/**
 * A type of feedback which may be a self-assessment.
 */
interface ISelfAssessableFeedback extends IFeedback
{
    /**
     * Was this feedback a self-assessment of the participant's own work?
     *
     * @return bool
     */
    public function isSelfAssessment(): bool;
}
