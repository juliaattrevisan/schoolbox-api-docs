<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
