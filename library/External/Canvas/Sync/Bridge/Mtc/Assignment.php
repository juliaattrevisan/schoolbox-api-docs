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
}
