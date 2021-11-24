<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\Bridge\Mtc;

use Schoolbox\Learning\Assessment\WorkType;

class Assignment extends \External\Canvas\Sync\Bridge\Assignment
{
    /**
     * @inheritDoc
     */
    public function getQuizWorkType(string $quizType): ?WorkType\Model
    {
        return $this->workTypes->offsetGet('Quiz');
    }

    /**
     * In the case of newington, we should be capturing the details but we shouldn't be allowing users to submit online.
     * @inheritDoc
     */
    public function getAcceptOnline(string $submissionTypes): bool
    {
        return false;
    }
}
