<?php

namespace Tests\Support\Payload\LearningMoment;

use Tests\Support\Payload\DeleteTrait;
use Tests\Support\Payload\Pastoral\FormTrait;
use Tests\Support\Payload\Request;

class Delete extends Request
{
    use DeleteTrait;
    use FormTrait;

    private int $evidenceId;

    public function __construct(int $studentId, int $evidenceId)
    {
        $this->evidenceId =  $evidenceId;
        $this->setReferer("/search/user/$studentId");
        $this->setHeader('content-type', 'application/json');
    }


    protected function getUrl(): string
    {
        return "/learning/evidence/$this->evidenceId";
    }
}
