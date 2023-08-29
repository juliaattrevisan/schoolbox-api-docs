<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Support\Payload\Pastoral;

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

    public function setPastoralSubType(?int $subType): void
    {
        $this->setFormParam('subtypeId', $subType);
    }

    public function setPastoralActions(int $actions): void
    {
        $this->setFormParam('actions[]', $actions);
    }

    public function setUserAccess(int $userId, int $accessLevel): void
    {
        $this->setAccess('user', $userId, $accessLevel);
    }

    public function setUserCommentsAllowed(int $userId): void
    {
        $this->setFormParam("userComment$userId", '1');
    }

    public function setRoleAccess(int|string $roleId, int $accessLevel): void
    {
        $this->setAccess('role', $roleId, $accessLevel);
    }

    public function setGroupAccess(int $groupId, int $accessLevel): void
    {
        $this->setAccess('group', $groupId, $accessLevel);
    }

    //to create records with match campus on - pass the campus id
    public function setRoleMatchCampus(): void
    {
        $this->setFormParam("roleCampus2", '1');
    }

    public function setDuplicateToUser(int $otherStudentId): void
    {
        $this->setFormParam('duplicate_id[]', $otherStudentId);
    }

    private function fillAccess(GenericCollection $accessRules): void
    {
        foreach ($accessRules as $accessRule) {
            if ($accessRule->getUser()) {
                $this->setUserAccess($accessRule->getUser()->getId(), $accessRule->getAccess());
            } elseif ($accessRule->getRole()) {
                $this->setRoleAccess($accessRule->getRole()->getId(), $accessRule->getAccess());
            } elseif ($accessRule->getGroup()) {
                $this->setGroupAccess($accessRule->getGroup()->getId(), $accessRule->getAccess());
            }
        }
    }

    private function setAccess(string $type, int|string $id, int $accessLevel): void
    {
        $this->setFormParam(sprintf('%s[%s]', $type, $id), $accessLevel);
    }
}
