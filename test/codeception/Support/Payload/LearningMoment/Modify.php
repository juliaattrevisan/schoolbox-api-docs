<?php

declare(strict_types=1);

namespace Tests\Support\Payload\LearningMoment;

use Tests\Support\Payload\Pastoral\FormTrait;
use Tests\Support\Payload\PutTrait;
use Tests\Support\Payload\Request;

class Modify extends Request
{
    use PutTrait;
    use FormTrait;

    private int $evidenceId;

    public function __construct(int $evidenceId, string $type, string $body, ?string $file)
    {
        $this->evidenceId =  $evidenceId;
        $this->setReferer("/learning/evidence/$evidenceId/modify");
        $this->setFormParam('body', $body);
        $this->setFormParam('file', $file);
        $this->setFormParam('type', $type);
        $this->setHeader('content-type', 'application/json');
    }


    protected function getUrl(): string
    {
        return "/learning/evidence/$this->evidenceId";
    }
}
