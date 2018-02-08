<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
