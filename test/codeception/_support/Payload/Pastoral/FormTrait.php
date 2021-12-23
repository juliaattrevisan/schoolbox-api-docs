<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Payload\Pastoral;

use Schoolbox\GenericCollection;

/**
 * Reusable implementation for interacting with the pastoral record create/update form
 */
trait FormTrait
{
    /**
     * @see \Payload\Request::setFormParam()
     * @param string $name
     * @param        $value
     */
    abstract public function setFormParam(string $name, $value): void;

    public function setHeading(string $heading): void
    {
        $this->setFormParam('heading', $heading);
    }

    public function setPastoralType(int $type): void
    {
        $this->setFormParam('typeId', $type);
    }

    public function setPastoralSubType(int $subType): void
    {
        $this->setFormParam('subtypeId', $subType);
    }

    public function setPastoralActions(int $actions): void
    {
        $this->setFormParam('actions[]', $actions);
    }

    public function setParentCommentsAllowed(): void
    {
        $this->setFormParam('parent comments allowed', '1');
    }

    public function setUserAccess(int $userId, int $accessLevel): void
    {
        $this->setAccess('user', $userId, $accessLevel);
    }

    public function setRoleAccess(int $roleId, int $accessLevel): void
    {
        $this->setAccess('role', $roleId, $accessLevel);
    }

    public function setGroupAccess(int $groupId, int $accessLevel): void
    {
        $this->setAccess('group', $groupId, $accessLevel);
    }

    public function setDuplicateToUser(int $otherStudentId): void
    {
        $this->setFormParam('duplicate_id[]', $otherStudentId);
    }

    private function fillAccess(GenericCollection $accessRules): void
    {
        foreach ($accessRules as $accessRule) {
            if ($accessRule->getUser()) {
                $this->setUserAccess($accessRule->getId(), $accessRule->getAccess());
            } elseif ($accessRule->getRole()) {
                $this->setRoleAccess($accessRule->getId(), $accessRule->getAccess());
            } elseif ($accessRule->getGroup()) {
                $this->setGroupAccess($accessRule->getId(), $accessRule->getAccess());
            }
        }
    }

    private function setAccess(string $type, int $id, int $accessLevel): void
    {
        $this->setFormParam(sprintf('%s[%d]', $type, $id), $accessLevel);
    }
}
