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
    /** @var string */
    protected $description;

    /** @var bool */
    protected $hidden;

    /** @var Alaress_Date_Format */
    protected $modifiedAt;

    /** @var int */
    protected $modifiedById;


    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }


    /**
     * @param string $description
     * @return self
     */
    public function setDescription($description)
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
     * @return int|null
     */
    public function getModifiedById(): ?int
    {
        return $this->modifiedById;
    }

    /**
     * @param $modifiedById
     * @return self
     */
    public function setModifiedById($modifiedById): self
    {
        $this->modifiedById = $modifiedById;
        return $this;
    }

}
