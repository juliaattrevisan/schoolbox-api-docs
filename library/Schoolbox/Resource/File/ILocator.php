<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\File;


use Schoolbox\Collection;

interface ILocator
{

    /**
     * Return the folder by the given ID
     *
     * @param int $nodeId
     * @return IFile
     */
    public function findById($nodeId);

    /**
     * Return the files contained within the folder with the given ID
     *
     * @param int $nodeId
     * @return Collection|IFile[]
     */
    public function findByFolderId($nodeId);

} 