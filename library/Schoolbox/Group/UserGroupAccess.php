<?php

/**
 *
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Group;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * Class to store user group membership as a single object
 */
class UserGroupAccess extends UserAccess
{
    /** @var Folder|null folder of the sid */
    private ?Folder $folder = null;

    /**
     * This is to present notification actually set in msg_reroute_override
     * @var bool
     */
    private bool $following = false;

    /**
     * @return Folder|null
     */
    public function getFolder(): ?Folder
    {
        return $this->folder;
    }

    /**
     * @param Folder $folder
     * @return $this
     */
    public function setFolder(Folder $folder): self
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return bool
     */
    public function getFollowing(): bool
    {
        return $this->following;
    }

    /**
     * @param bool $following
     * @return $this
     */
    public function setFollowing(bool $following): self
    {
        $this->following = $following;
        return $this;
    }
}
