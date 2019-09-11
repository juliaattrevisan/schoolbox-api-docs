<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Pastoral;

use Payload\PostTrait;
use Payload\Request;
use Schoolbox\Pastoral;

class Update extends Request
{
    use PostTrait;
    use FormTrait;

    private $pastoral;

    public function __construct(Pastoral\Model $pastoral)
    {
        $this->pastoral = $pastoral;

        $this->setReferer("/pastoral/student/{$pastoral->getStudent()->getId()}/record/{$pastoral->getId()}/update");

        $this->setFormParam('id', $pastoral->getId());
        $this->setFormParam('student_id', $pastoral->getStudent()->getId());
        $this->setFormParam('heading', $pastoral->getHeading());
        $this->setFormParam('typeId', $pastoral->getType()->getId());

        $subType = $pastoral->getSubType();
        $this->setFormParam('subtypeId', $subType === null ? '' : (string)$subType->getId());
        $severity = $pastoral->getSeverity();
        $this->setFormParam('severityId', $severity === null ? '' : (string)$severity->getId());
        $this->setFormParam('points', $pastoral->getPoints());
        $this->setFormParam('tags', implode(',', $pastoral->getTags()->getPropertyValues('id')));
        $this->setFormParam('body_public', $pastoral->getBodyPublic());
        $this->setFormParam('body_private', $pastoral->getBodyPrivate());

        if ($pastoral->getAccess() !== null) {
            $this->fillAccess($pastoral->getAccess());
        }
    }

    protected function getUrl(): string
    {
        return "/pastoral/record/{$this->pastoral->getId()}/update";
    }
}
