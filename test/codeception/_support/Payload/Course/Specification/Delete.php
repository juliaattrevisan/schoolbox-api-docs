<?php


namespace Payload\Course\Specification;


use Payload\PostTrait;

class Delete extends \Payload\Request
{

    use PostTrait;

    private int $courseId;
    private int $assessmentId;

    public function __construct(int $courseId,int $assessmentId)
    {

        $this->courseId = $courseId;
        $this->assessmentId = $assessmentId;
        $this->setReferer('/course/modify/'. $courseId);
        $this->setHeader('content-type','application/json');
    }

    protected function getUrl(): string
    {
        return '/learning/assessments/' .$this->assessmentId .'/delete';

    }
}
