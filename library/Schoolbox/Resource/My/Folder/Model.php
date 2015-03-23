<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\My\Folder;


use Schoolbox\Resource\AbstractFolder;
use Schoolbox\User;

class Model extends AbstractFolder
{

    /** @var User */
    protected $owner;


    /**
     * @return User
     */
    public function getOwner()
    {
        return $this->owner;
    }


    /**
     * @param User $owner
     * @return self
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
        return $this;
    }


    /**
     * A folder type. This will probably map to a class name.
     *
     * @return string
     */
    public function getFolderType()
    {
        return 'folder';
    }

}