<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Folder\Log;

use Alaress_Date_Format;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

class Model
{
    /**
     * @var Folder\Model
     */
    protected $folder;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var Alaress_Date_Format
     */
    protected $last_access_time;

    /**
     * @var int
     */
    protected $hitcount;

    /**
     * Auto-generated methods
     */

    /**
     * @param  Folder\Model $folder
     *
     * @return $this
     */
    public function setFolder(Folder\Model $folder): self
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return Folder\Model
     */
    public function getFolder(): ?Folder\Model
    {
        return $this->folder;
    }

    /**
     * @param  int $hitcount
     *
     * @return $this
     */
    public function setHitcount($hitcount): self
    {
        $this->hitcount = $hitcount;
        return $this;
    }

    /**
     * @return int
     */
    public function getHitcount(): ?int
    {
        return $this->hitcount;
    }

    /**
     * @param  Alaress_Date_Format $last_access_time
     *
     * @return $this
     */
    public function setLastAccessTime($last_access_time): self
    {
        $this->last_access_time = $last_access_time;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getLastAccessTime(): ?Alaress_Date_Format
    {
        return $this->last_access_time;
    }

    /**
     * @param  User $user
     *
     * @return $this
     */
    public function setUser($user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser(): ?User
    {
        return $this->user;
    }
}
