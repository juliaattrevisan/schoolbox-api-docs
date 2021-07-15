<?php

declare(strict_types=1);

namespace Payload\LearningMoment;

use Payload\Pastoral\FormTrait;
use Payload\PutTrait;
use Payload\Request;

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
