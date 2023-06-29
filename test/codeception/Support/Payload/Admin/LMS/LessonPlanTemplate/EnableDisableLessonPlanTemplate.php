<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Support\Payload\Admin\LMS\LessonPlanTemplate;

use Tests\Support\Payload\PostTrait;

class EnableDisableLessonPlanTemplate extends \Tests\Support\Payload\Request
{
    use PostTrait;

    /**
     * The lesson plan template ID to enable/disable.
     */
    private int $lessonPlanTemplateId;

    /**
     * @param int $lessonPlanTemplateId
     * This function deletes a lesson plan template by passing its id
     */
    public function __construct(int $lessonPlanTemplateId)
    {
        $this->lessonPlanTemplateId = $lessonPlanTemplateId;
        $this->setReferer("/adminv2/lesson-plan/template");
    }


    protected function getUrl(): string
    {
        return "/adminv2/lesson-plan/template/toggle/$this->lessonPlanTemplateId";
    }
}
