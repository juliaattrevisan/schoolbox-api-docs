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
 * Interface IFileMapper
 *
 * Must be implemented by all mappers that have a model that implements IFile
 *
 * @package Schoolbox\Resource\File
 */
interface IFileMapper extends IStorageWrapper
{

    /**
     * @return Storage\Mapper
     */
    public function getStorageMapper();
}
