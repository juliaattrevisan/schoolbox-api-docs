<?php

namespace Tests\Support\Payload\Course\Specification;

use Tests\Support\Payload\PostTrait;

class Delete extends \Tests\Support\Payload\Request
{
    use PostTrait;

    private int $courseId;
    private int $assessmentId;

    public function __construct(int $courseId, int $assessmentId)
    {

        $this->courseId = $courseId;
        $this->assessmentId = $assessmentId;
        $this->setReferer('/course/modify/' . $courseId);
        $this->setHeader('content-type', 'application/json');
    }

    protected function getUrl(): string
    {
        return '/learning/assessments/' . $this->assessmentId . '/delete';
    }
}
