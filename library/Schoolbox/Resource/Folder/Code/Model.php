<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Folder\Code;


use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreateCollection;
use Schoolbox\Resource\Folder;

class Model implements ISoftDeletable
{
    use CreateCollection;

    // Maximum length allowed for folder codes
    public const CODE_LENGTH_MAX = 20;

    /**
     * @var Folder\Model
     */
    protected $folder;

    /**
     * @var string
     */
    protected $code;

    /**
     * @var \Alaress_Date_Format
     */
    protected $deletedAt;

    /**
     * @param  Folder\Model $folder
     * @return $this
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return Folder\Model
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param  string $code
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param \Alaress_Date_Format $deletedAt
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * @return bool
     */
    public function isDeleted()
    {
        return ($this->getDeletedAt() instanceof \Alaress_Date_Format && !$this->getDeletedAt()->isNull());
    }

}
