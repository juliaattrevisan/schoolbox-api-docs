<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Admin\LMS\LessonPlanTemplate;

use Payload\PostTrait;

class EnableDisableLessonPlanTemplate extends \Payload\Request
{
    use PostTrait;

    /**
     * @param int $lessonPanTemplateId
     * This function deletes a lesson plan template by passing its id
     */
    public function __construct(int $lessonPanTemplateId)
    {

        $this->lessonPanTemplateId = $lessonPanTemplateId;
        $this->setReferer("/adminv2/lesson-plan/template");
    }


    protected function getUrl(): string
    {
        return "/adminv2/lesson-plan/template/toggle/$this->lessonPanTemplateId";
    }
}
