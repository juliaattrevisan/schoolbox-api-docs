<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Admin\LMS\LessonPlanTemplate;

use Payload\DeleteTrait;

class DeleteLessonPlanTemplate extends \Payload\Request
{
    use DeleteTrait;

    private int $lessonPanTemplateId;

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
        return "/adminv2/lesson-plan/template/delete/$this->lessonPanTemplateId";
    }
}
