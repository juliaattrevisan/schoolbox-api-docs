<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder\Log;

use Alaress_Date_Format;
use \Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\User;

class Model
{
    /**
     * @var FolderModel
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
     * @param  FolderModel $folder
     *
     * @return $this
     */
    public function setFolder(FolderModel $folder): self
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return FolderModel
     */
    public function getFolder(): FolderModel
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
    public function getHitcount(): int
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
    public function getLastAccessTime(): Alaress_Date_Format
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
    public function getUser(): User
    {
        return $this->user;
    }

}