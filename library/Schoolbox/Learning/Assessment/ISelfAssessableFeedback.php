<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use UnexpectedValueException;

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


    /**
     * Returns whether this feedback was created via the learner self-reflect work-flow
     *
     * @return bool
     * @throws UnexpectedValueException If property is not set
     */
    public function isSelfReflectionOnly(): bool;


    /**
     * Returns whether this feedback was created via the learner self-grade work-flow
     *
     * @return bool
     * @throws UnexpectedValueException If model property is not hydrated
     */
    public function isSelfGrading(): bool;
}
