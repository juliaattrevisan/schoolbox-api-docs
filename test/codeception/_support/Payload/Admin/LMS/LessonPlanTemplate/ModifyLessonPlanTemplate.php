<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Admin\LMS\LessonPlanTemplate;

use Payload\PostTrait;

class ModifyLessonPlanTemplate extends \Payload\Request
{
    use PostTrait;

    /**
     * modify a lesson plan template by passing the lesson plan id, name and lesson fields details
     * @param string $lessonPlanTemplateName
     * @param string $fieldName
     * @param string $visibility
     * @param string $body
     */
    public function __construct(string $lessonPlanTemplateName, int $lessonPanTemplateId, array $lessons)
    {
        $this->lessonPanTemplateId = $lessonPanTemplateId;
        $this->setReferer("/adminv2/lesson-plan/template/update/$this->lessonPanTemplateId");
        //lesson plan title
        $this->setFormParam('title', $lessonPlanTemplateName);
        //create one or more lesson plan fields
        foreach ($lessons as $lesson) {
            $this->setFormParam('field[' . $lesson[0] . '][title]', $lesson[1]);
            $this->setFormParam('field[' . $lesson[0] . '][visibility]', $lesson[2]);
            $this->setFormParam('field[' . $lesson[0] . '][body]', $lesson[3]);
        }
    }

    protected function getUrl(): string
    {
        return "/adminv2/lesson-plan/template/update/$this->lessonPanTemplateId";
    }
}
