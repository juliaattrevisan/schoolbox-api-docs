<?php

/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\CMS\Log;

use Schoolbox\Resource\File\Model as File;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Textbox\Model as Textbox;
use Schoolbox\Resource\My\File\Model as myFile;
use Schoolbox\Resource\My\Folder\Model as myFolder;
use Schoolbox\Resource\Link;
use Schoolbox\User;

class Model
{
    /** @var int */
    protected $id;

    /** @var string */
    protected $object;

    /** @var int */
    protected $objectId;

    /** @var File */
    protected $file;

    /** @var Folder */
    protected $folder;

    /** @var Textbox */
    protected $textbox;

    /** @var myFile */
    protected $myFile;

    /** @var myFolder */
    protected $myFolder;

    /** @var Link */
    protected $link;

    /** @var string */
    protected $action;

    /** @var User */
    protected $user;

    /** @var \Alaress_Date_Format */
    protected $time;

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
     * @param string $object
     * @return self
     */
    public function setObject($object)
    {
        $this->object = $object;
        return $this;
    }

    /**
     * @return string
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @param string $objectId
     * @return self
     */
    public function setObjectId($objectId)
    {
        $this->objectId = $objectId;
        return $this;
    }

    /**
     * @return string
     */
    public function getObjectId()
    {
        return $this->objectId;
    }

    /**
     * @param File $file
     * @return self
     */
    public function setFile($file)
    {
        $this->file = $file;
        return $this;
    }

    /**
     * @return File
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @param Folder $folder
     * @return self
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return Folder
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param Textbox $textbox
     * @return self
     */
    public function setTextbox($textbox): self
    {
        $this->textbox = $textbox;
        return $this;
    }

    /**
     * @return Textbox
     */
    public function getTextbox()
    {
        return $this->textbox;
    }

    /**
     * @param myFile $myFile
     * @return self
     */
    public function setMyFile($myFile): self
    {
        $this->myFile = $myFile;
        return $this;
    }

    /**
     * @return myFile
     */
    public function getMyFile()
    {
        return $this->myFile;
    }

    /**
     * @param myFolder $myFolder
     * @return self
     */
    public function setMyFolder($myFolder): self
    {
        $this->myFolder = $myFolder;
        return $this;
    }

    /**
     * @return myFolder
     */
    public function getMyFolder()
    {
        return $this->myFolder;
    }

    /**
     * @param Link $link
     * @return self
     */
    public function setLink($link)
    {
        $this->link = $link;
        return $this;
    }

    /**
     * @return Link
     */
    public function getLink()
    {
        return $this->link;
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
     * @param \Alaress_Date_Format $time
     * @return self
     */
    public function setTime($time)
    {
        $this->time = $time;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getTime()
    {
        return $this->time;
    }
}
