<?php

declare(strict_types=1);

namespace Payload\LearningMoment;

use Payload\Pastoral\FormTrait;
use Payload\PostTrait;
use Payload\Request;

class Create extends Request
{

    use PostTrait;
    use FormTrait;

    private int $studentId;

    public function __construct(int $studentId, string $type, string $body)
    {
        $this->studentId =  $studentId;
        $file= null;
        $this->setReferer("/learning/evidence/user/$studentId/create?momentType=$type");
        $this->setFormParam('body', $body);
        $this->setFormParam('file', $file);
        $this->setFormParam('type', $type);
        $this->setHeader('content-type','application/json');
    }

    protected function getUrl(): string
    {
        return "/learning/evidence/user/$this->studentId/create";
    }
}
