<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource;


use Schoolbox\FolderInterface;

interface FolderLocatorInterface {

    /**
     * Return the folder with the given ID
     *
     * @param int $nodeId
     * @return FolderInterface
     */
    public function findById($nodeId);


    /**
     * Return the folders contained within the folder with the given ID
     *
     * @param int $nodeId
     * @return FolderInterface[]
     */
    public function findByParentId($nodeId);
} 