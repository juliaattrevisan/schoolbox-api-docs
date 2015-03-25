<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\File;

use Schoolbox\Resource\Storage;

/**
 * Interface IStorageWrapper
 *
 * Must be implemented by mappers that depend on Storage.
 * Provides a method for returning the mapper's model wrapped around the given storage object.
 *
 * @package Schoolbox\Resource\File
 */
interface IStorageWrapper
{

    /**
     * Wrap a given storage model in your appropriate filetype
     *
     * @param Storage\Model $storage
     * @return IFile
     */
    public function wrap(Storage\Model $storage);

} 