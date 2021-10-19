<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\Bridge\Newic;

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
     * @inheritDoc
     */
    public function getQuizWorkType(string $quizType): ?WorkType\Model
    {
        return $this->workTypes->offsetGet('Quiz');
    }
}
