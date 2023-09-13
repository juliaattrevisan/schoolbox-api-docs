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
     * @return array{id: int, name: string, courseTempalte: ?array{id: string, name: string}, unitTemplate: ?array{id: string, name: string}}
     */
    public function __invoke(Model $courseTemplate): array
    {
        $course = [
            'id'    => $courseTemplate->getCourseTemplate()?->getId(),
            'name'  => $courseTemplate->getCourseTemplate()?->getName(),
        ];
        $unit = [
            'id'    => $courseTemplate->getUnitTemplate()?->getId(),
            'name'  => $courseTemplate->getUnitTemplate()?->getName(),
        ];
        return [
            'id'                => $courseTemplate->getId(),
            'name'              => $courseTemplate->getName(),
            'courseTemplate'    => $courseTemplate->getCourseTemplate() ? $course : null,
            'unitTemplate'      => $courseTemplate->getUnitTemplate() ? $unit : null,
        ];
    }
}
