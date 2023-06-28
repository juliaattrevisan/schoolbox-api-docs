<?php

namespace Tests\Support\Payload\Course\Unit\Specification;

use Tests\Support\Payload\PostTrait;
use Tests\Support\Payload\Request;

class Delete extends Request
{
    use PostTrait;

    private int $courseId;
    private int $unitId;

    public function __construct(int $courseId, int $unitId)
    {

        $this->courseId = $courseId;
        $this->unitId = $unitId;
        $this->setReferer('/course/modify/' . $courseId . '#' . $unitId);
        $this->setHeader('content-type', 'application/json');
    }

    protected function getUrl(): string
    {
        return '/course/unitspecs/' . $this->unitId . '/delete';
    }
}
