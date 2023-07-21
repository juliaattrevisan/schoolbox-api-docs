<?php

namespace Tests\Support\Payload\LessonPlan;

use Tests\Support\Payload\PostTrait;

class Delete  extends \Tests\Support\Payload\Request
{
    use PostTrait;

    private int $assessmentId;


    /**
     * @param int $assessmentId
     */
    public function __construct(int $assessmentId)
    {
        $this->assessmentId =  $assessmentId;
        $this->setReferer("/learning/assessments/$assessmentId/modify?returnUrl=");
    }

    protected function getUrl(): string
    {
        return "/learning/assessments/$this->assessmentId/delete";
    }
}
