<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Payload\Group;

use Schoolbox\Permission\Access\UserAccess;

class ModifyAccessLevel extends GroupMember
{
    public function setMemberAccessLevel(int $user_id, int $accessLevel): void
    {
        $this->setFormParam("accessTypes[{$user_id}]", ['userId' => $user_id, 'accessType' => UserAccess::ACCESS_LEVEL_TO_NAME[$accessLevel]]);
    }

    /** @inheritDoc */
    protected function getUrl(): string
    {
        return "/group/updateMembers/{$this->folderId}";
    }
}
