<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource;

use Schoolbox\User;

class Log
{

    /** @var int */
    protected $id;

    /** @var int */
    protected $objectId;

    /** @var string */
    protected $objectKind;

    /** @var string */
    protected $action;

    /** @var User */
    protected $user;

    /** @var \Alaress_Date_Format */
    protected $timestamp;

    /**
     * @param int $id
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $objectId
     */
    public function setObjectId($objectId)
    {
        $this->objectId = $objectId;
    }

    /**
     * @return int
     */
    public function getObjectId()
    {
        return $this->objectId;
    }

    /**
     * @param string $objectKind
     */
    public function setObjectKind($objectKind)
    {
        $this->objectKind = $objectKind;
    }

    /**
     * @return string
     */
    public function getObjectKind()
    {
        return $this->objectKind;
    }


    /**
     * @param Link|File\Model|Folder\Model|Textbox\Model $object
     * @return self
     */
    public function setObject($object)
    {
        // splice this out to be kind and ID
        if ($object instanceof File\Model) {
            $this->objectId = $object->getId();
            $this->objectKind = LogMapper::KIND_FILE;
        } elseif ($object instanceof Link) {
            $this->objectId = $object->getId();
            $this->objectKind = LogMapper::KIND_LINK;
        } elseif ($object instanceof Folder\Model) {
            $this->objectId = $object->getId();
            $this->objectKind = LogMapper::KIND_FOLDER;
        } elseif ($object instanceof Textbox\Model) {
            $this->objectId = $object->getId();
            $this->objectKind = LogMapper::KIND_CONTENT;
        } else {
            // TODO support for MyFile and MyFolder
        }
        return $this;
    }


    /**
     * @return Link|File\Model|Folder\Model|Textbox\Model
     */
    public function getObject()
    {
        switch ($this->objectKind) {
            case LogMapper::KIND_LINK:
                return LinkMapper::getInstance()->findByIdWithFallback($this->objectId);
            case LogMapper::KIND_FILE:
                return File\Mapper::getInstance()->findByIdWithFallback($this->objectId);
            case LogMapper::KIND_FOLDER:
                return Folder\Mapper::getInstance()->findByIdWithFallback($this->objectId);
            case LogMapper::KIND_CONTENT:
                return Textbox\Mapper::getInstance()->findByIdWithFallback($this->objectId);
        }
        return null;
    }


    /**
     * @param string $action
     * @return self
     */
    public function setAction($action)
    {
        $this->action = $action;
        return $this;
    }


    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }


    /**
     * @param User $user
     * @return self
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }


    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }


    /**
     * @param \Alaress_Date_Format $timestamp
     */
    public function setTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;
    }


    /**
     * @return \Alaress_Date_Format
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }
}
