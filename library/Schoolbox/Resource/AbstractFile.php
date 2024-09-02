<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\User;

/**
 * Class AbstractFile
 * Provides common file-based functionality for files stored in a directory structure (ie. can have an IFolder parent)
 *
 * @package Schoolbox\Resource
 */
abstract class AbstractFile implements IFile, ISoftDeletable
{
    use StorableTrait;
    use CreatedAtDate;
    use DeletedAtDate;

    /** @var string|null $title */
    protected ?string $title;

    /** @var User $owner */
    protected $owner;

    /** @var IFolder $folder */
    protected $folder;

    /**
     * @return IFolder
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param IFolder $folder
     *
     * @return self
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;

        return $this;
    }

    /**
     * @return User
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param User $owner
     *
     * @return self
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string|null $title
     *
     * @return self
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @param boolean $hidden
     * @return self
     */
    public function setHidden($hidden)
    {
        return $this;
    }
}
