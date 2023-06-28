<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Support\Payload\Admin\LMS\LessonPlanTemplate;

use Tests\Support\Payload\PostTrait;

class ModifyLessonPlanTemplate extends \Tests\Support\Payload\Request
{
    use PostTrait;


    /**
     * The lesson plan template ID to enable/disable.
     */
    private int $lessonPlanTemplateId;

    /**
     * modify a lesson plan template by passing the lesson plan id, name and lesson fields details
     * @param string $lessonPlanTemplateName
     * @param string $fieldName
     * @param string $visibility
     * @param string $body
     */
    public function __construct(string $lessonPlanTemplateName, int $lessonPlanTemplateId, array $lessons)
    {
        $this->lessonPlanTemplateId = $lessonPlanTemplateId;
        $this->setReferer("/adminv2/lesson-plan/template/update/$this->lessonPlanTemplateId");
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
        return "/adminv2/lesson-plan/template/update/$this->lessonPlanTemplateId";
    }
}
