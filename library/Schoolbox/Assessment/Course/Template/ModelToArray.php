<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Template;

/**
 * Translates CourseTemplate Models to an array to be used by vue
 *
 */
class ModelToArray
{
    /**
     * @param Model $courseTemplate
     * @return array{id: int, name: string, courseTemplate: array{id: int, name: string}, unitTemplate: array{id: int, name: string}}
     */
    public function __invoke(Model $courseTemplate): array
    {
        return [
            'id'                => $courseTemplate->getId(),
            'name'              => $courseTemplate->getName(),
            'sequence'          => $courseTemplate->getSequence(),
            'courseTemplate'    => [
                'id'        => $courseTemplate->getCourseTemplate()->getId(),
                'name'      => $courseTemplate->getCourseTemplate()->getName(),
                'deleted'   => $courseTemplate->getCourseTemplate()->isDeleted(),
            ],
            'unitTemplate'      => [
                'id'        => $courseTemplate->getUnitTemplate()->getId(),
                'name'      => $courseTemplate->getUnitTemplate()->getName(),
                'deleted'   => $courseTemplate->getUnitTemplate()->isDeleted(),
            ],
        ];
    }
}
