<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use UnexpectedValueException;

/**
 * An assessment type which is able to be self-assessed.
 */
interface ISelfAssessable extends IAssessable
{
    /**
     * Can learners give feedback on their own responses?
     *
     * @return bool
     */
    public function isSelfAssessable(): bool;


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
    public function isSelfGradable(): bool;
}
