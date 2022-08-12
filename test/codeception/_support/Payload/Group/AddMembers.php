<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Payload\Group;

use Schoolbox\Permission\Access\UserAccess;

class AddMembers extends GroupMember
{
    public function setNewMembers(array $userIds): void
    {
        foreach ($userIds as $idx => $userId) {
            $this->setFormParam("members[$idx]", $userId);
            $this->setFormParam("accessType", UserAccess::ACCESS_LEVEL_TO_NAME[UserAccess::ACCESS_READ]);
        }
    }

    /** @inheritDoc */
    protected function getUrl(): string
    {
        return "/group/addMembers/{$this->folderId}";
    }
}
