<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\SubjectReport\Components;

use Schoolbox\Learning\Assessment\DueWork;

/**
 * Class LearningProfile
 */
class LearningProfile extends AbstractMatrix
{
    public function buildAssessmentData(DueWork\Model $dueWork): array
    {
        return parent::buildAssessmentData($dueWork);
    }
}
