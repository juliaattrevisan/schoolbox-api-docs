<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource;


use Schoolbox\FileInterface;

interface FileLocatorInterface {

    /**
     * Return the folder by the given ID
     *
     * @param int $nodeId
     * @return FileInterface
     */
    public function findById($nodeId);

    /**
     * Return the files contained within the folder with the given ID
     *
     * @param int $nodeId
     * @return FileInterface[]
     */
    public function findByFolderId($nodeId);

} 