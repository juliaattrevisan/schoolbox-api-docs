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