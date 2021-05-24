<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\File;

use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\User;

/**
 * Interface IFile
 * Provides common access methods across all File implementations
 *
 * @package Schoolbox\Resource\File
 */
interface IFile extends IStorage
{
    /**
     * Returns the ID of the file
     *
     * @return int
     */
    public function getId();

    /**
     * @return User
     */
    public function getOwner();

    /**
     * @return IStorage
     */
    public function getStorage();
}
