<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Admin\LMS\LessonPlanTemplate;

use Payload\PostTrait;

class CreateLessonPlanTemplate extends \Payload\Request
{
    use PostTrait;

    /**
     * create a lesson plan template by passing the lesson plan name and lesson fields details
     * @param string $lessonPlanTemplateName
     * @param string $fieldName
     * @param string $visibility
     * @param string $body
     */
    public function __construct(string $lessonPlanTemplateName, array $lessons)
    {
        $this->setReferer("/adminv2/lesson-plan/template/create");
        //lesson plan title
        $this->setFormParam('title', $lessonPlanTemplateName);
        //create one or more lesson plan fields
        foreach ($lessons as $lesson) {
            $this->setFormParam('field[new' . $lesson[0] . '][title]', $lesson[1]);
            $this->setFormParam('field[new' . $lesson[0] . '][visibility]', $lesson[2]);
            $this->setFormParam('field[new' . $lesson[0] . '][body]', $lesson[3]);
        }
    }

    protected function getUrl(): string
    {
        return "/adminv2/lesson-plan/template/create";
    }
}
