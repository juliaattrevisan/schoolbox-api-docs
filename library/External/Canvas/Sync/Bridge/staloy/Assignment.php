<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\Bridge\staloy;

use Schoolbox\Learning\Assessment\WorkType;

class Assignment extends \External\Canvas\Sync\Bridge\Assignment
{

    /**
     * @inheritDoc
     */
    public function syncCanvasQuizzes(): bool
    {
        return false;
    }

    /**
     * Returns the work type to assign to this quiz type
     *
     * @param string $quizType
     * @return null|WorkType\Model
     */
    public function getQuizWorkType(string $quizType): ?WorkType\Model
    {
        $config = [
            self::QUIZ_TYPE_PRACTICE      => 5,
            self::QUIZ_TYPE_ASSIGNMENT    => 5,
            self::QUIZ_TYPE_SURVEY        => 5,
            self::QUIZ_TYPE_GRADED_SURVEY => 5,
        ];
        if (!array_key_exists($quizType, $config)) {
            return null;
        }
        $workTypeId = $config[$quizType];
        foreach ($this->workTypes as $workType) {
            if ($workType->getId() === $workTypeId) {
                return $workType;
            }
        }
        return null;
    }
}
