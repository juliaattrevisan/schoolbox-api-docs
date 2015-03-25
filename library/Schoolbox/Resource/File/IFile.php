<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\File;


use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\User;

/**
 * Interface IFile
 *
 * Provides common access methods across all File implementations
 *
 * @package Schoolbox\Resource\File
 */
interface IFile extends IStorage
{

    /**
     * Returns the ID of the file
     * @return int
     */
    public function getId();

    /**
     * Returns the real filename of the file
     * @return string
     */
    public function getFilename();

    /**
     * @return User
     */
    public function getOwner();

    /**
     * @return Storage
     */
    public function getStorage();

} 