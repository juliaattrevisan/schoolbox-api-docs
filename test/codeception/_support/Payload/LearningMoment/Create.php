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

    public function __construct(int $studentId, string $type)
    {
        $cars = array();
        $this->setReferer("/learning/evidence/user/$studentId/create?momentType=$type");
        $this->setFormParam('body', 'Learning Moment Text');
        $this->setFormParam('file', null);
        $this->setFormParam('curriculumNodes', $cars);
        $this->setFormParam('tags', $cars);
        $this->setFormParam('type', 'text');
        $this->setHeader('content-type','application/json');
    }

    public function setBody(string $body): void
    {
//        $this->setFormParam('body', $body);
    }

    protected function getUrl(): string
    {
        return '/learning/evidence/user/25/create';
    }
}
