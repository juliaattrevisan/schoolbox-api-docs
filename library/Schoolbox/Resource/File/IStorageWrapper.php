<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
