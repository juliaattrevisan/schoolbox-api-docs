<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Storage;


interface Receiver
{

    /**
     * Accept a new file upload
     *
     * @param string $filename
     * @param string $tempLocation
     * @param bool   $public
     * @return Model
     */
    public function accept($filename, $tempLocation, $public = false);

} 