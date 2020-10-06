<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Grades\Translator;

use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;

/**
 * A translator class used to convert a grade group to a JSON representation.
 */
class GradeGroup
{
    public function __invoke(GradeLevel\Group\Model $model): array
    {
        return [
            'id'     => $model->getId(),
            'name'   => $model->getName(),
            'levels' => $model->getGradeLevels()->map(static function (GradeLevel\Model $e) {
                return [
                    'id'         => $e->getId(),
                    'lowerBound' => $e->getLowerBound(),
                    'rawGrade'   => $e->getRawGrade(),
                    'caption'    => $e->getCaption(),
                ];
            })
        ];
    }
}
