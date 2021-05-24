<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\Bridge\Mtc;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\WorkType;

class Assignment extends \External\Canvas\Sync\Bridge\Assignment
{
    /**
     * @inheritDoc
     */
    public function __construct(GenericCollection $markTypes, GenericCollection $workTypes)
    {
        parent::__construct($markTypes, $workTypes);
        // reorder $workTypes
        $this->workTypes->indexBy('name');
    }

    /**
     * @inheritDoc
     */
    public function getSubmissionBoxWorkType(string $workTypeName): ?WorkType\Model
    {
        return $this->workTypes->offsetGet($workTypeName);
    }

    /**
     * Returns the work type to assign to this quiz type
     *
     * @param string $quizType
     * @return null|WorkType\Model
     */
    public function getQuizWorkType(string $quizType): ?WorkType\Model
    {
        return $this->workTypes->offsetGet('Quiz');
    }
}
