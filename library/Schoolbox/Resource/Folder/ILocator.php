<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder;


use Schoolbox\Collection;

interface ILocator {

    /**
     * Return the folder with the given ID
     *
     * @param int $nodeId
     * @return IFolder
     */
    public function findById($nodeId);


    /**
     * Return the folders contained within the folder with the given ID
     *
     * @param int $nodeId
     * @return Collection|IFolder[]
     */
    public function findByParentId($nodeId);
} 