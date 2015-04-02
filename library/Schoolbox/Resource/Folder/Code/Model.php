<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder\Code;


use Schoolbox\ISoftDeletable;
use Schoolbox\Resource\Folder;

class Model implements ISoftDeletable
{
    /**
     * @var Folder\Model
     */
    protected $category;

    /**
     * @var string
     */
    protected $code;

    /**
     * @var \Alaress_Date_Format
     */
    protected $deletedAt;

    /**
     * @param  Folder\Model $category
     * @return $this
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return Folder\Model
     */
    public function getCategory()
    {
        return $this->category;
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
