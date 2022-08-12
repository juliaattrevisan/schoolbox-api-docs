<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Payload\Group;

use Payload\PostTrait;
use Payload\Request;

abstract class GroupMember extends Request
{
    use PostTrait;

    public int $folderId;

    public function __construct(int $folderId)
    {
        $this->setReferer("/group/modify/$folderId");
        $this->setFormParam('folderId', $folderId);
        $this->folderId = $folderId;
    }

    /** @inheritDoc */
    protected function getUrl(): string
    {
        return "/group/addMembers/{$this->folderId}";
    }
}
