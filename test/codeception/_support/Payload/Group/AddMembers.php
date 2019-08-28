<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Group;

class AddMembers extends GroupMember
{
    public function setNewMembers(array $userIds): void
    {
        foreach ($userIds as $idx => $userId) {
            $this->setFormParam("userID[$idx]", $userId);
        }
    }
}
