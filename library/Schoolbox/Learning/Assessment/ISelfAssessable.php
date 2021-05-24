<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

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
}
