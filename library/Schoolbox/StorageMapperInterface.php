<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox;


interface StorageMapperInterface {

    /**
     * Convenience method for creating and saving a file via this mapper.
     *
     * @param string $filename      the file's name.
     * @param string $tempLocation  the location of the file to save.
     * @param int    $userId        the user to associate with the file.
     * @throws File\Exception       if the upload was not allowed.
     * @return FileInterface
     */
    public function upload($filename, $tempLocation, $userId);

} 