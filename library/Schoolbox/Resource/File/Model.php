<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\File;

use Alaress_Date_Format;
use Schoolbox\Resource\AbstractFile;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

/**
 * Class Model
 *
 * The SBFS File Model. Represents a file within SBFS.
 *
 * @package Schoolbox\Resource\File
 * @method Folder\Model getFolder()
 */
class Model extends AbstractFile
{
    /** @var string|null */
    protected ?string $description = null;

    /** @var bool */
    protected $hidden;

    /** @var Alaress_Date_Format */
    protected $modifiedAt;

    /** @var User $modifiedBy */
    protected $modifiedBy;

    const FILTER_DOCUMENT = 'document';
    const FILTER_AUDIO    = 'audio';
    const FILTER_VIDEO    = 'video';
    const FILTER_IMAGE    = 'image';
    const FILTER_OTHER    = 'other';

    const FILTER_FIELDS = [
        self::FILTER_DOCUMENT,
        self::FILTER_AUDIO,
        self::FILTER_VIDEO,
        self::FILTER_IMAGE,
        self::FILTER_OTHER,
    ];

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }


    /**
     * @param string|null $description
     * @return self
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }


    /**
     * @return boolean
     */
    public function isHidden()
    {
        return $this->hidden;
    }


    /**
     * @deprecated exists for Mapper limitation
     * @return bool
     */
    public function getHidden()
    {
        return $this->isHidden();
    }


    /**
     * @param boolean $hidden
     * @return self
     */
    public function setHidden($hidden)
    {
        $this->hidden = $hidden;
        return $this;
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getModifiedAt()
    {
        return $this->modifiedAt;
    }


    /**
     * @param Alaress_Date_Format $modifiedAt
     * @return self
     */
    public function setModifiedAt($modifiedAt)
    {
        $this->modifiedAt = $modifiedAt;
        return $this;
    }

    /**
     * @return User
     */
    public function getModifiedBy()
    {
        return $this->modifiedBy;
    }

    /**
     * @param User $modifiedBy
     *
     * @return Model
     */
    public function setModifiedBy($modifiedBy): Model
    {
        $this->modifiedBy = $modifiedBy;

        return $this;
    }
}
