<?php

namespace Payload\LearningMoment;

use Payload\DeleteTrait;
use Payload\Pastoral\FormTrait;
use Payload\Request;

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
