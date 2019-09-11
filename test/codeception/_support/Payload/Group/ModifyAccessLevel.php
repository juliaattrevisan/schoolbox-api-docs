<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Group;

class ModifyAccessLevel extends GroupMember
{
    public function setMemberAccessLevel(int $user_id, int $accessLevel): void
    {
        $this->setFormParam('accessLevel['.$user_id.']', $accessLevel);
    }
}
