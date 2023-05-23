<?php

namespace Payload\Admin\LMS;

use Payload\DeleteTrait;
use Payload\Pastoral\FormTrait;

class DeleteLessonPlanTemplate extends \Payload\Request
{
    use DeleteTrait;
    use FormTrait;
    private int $badgeId;

    public function __construct(int $lessonPanTemplateId)
    {

        $this->lessonPanTemplateId = $lessonPanTemplateId;
        $this->setReferer("/adminv2/lesson-plan/template");
//        $this->setHeader('content-type','application/json');
    }


    protected function getUrl(): string
    {
        return "/adminv2/lesson-plan/template/delete/$this->lessonPanTemplateId";
    }
}
