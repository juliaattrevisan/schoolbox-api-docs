<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\File;


use Schoolbox\User;

interface IFile
{

    /**
     * Returns the full mime string for the file
     * @return string
     */
    public function getMime();

    /**
     * Returns the ID of the file
     * @return int
     */
    public function getId();

    /**
     * Returns the size on disk of the file
     * @return int
     */
    public function getSize();

    /**
     * Returns the real filename of the file
     * @return string
     */
    public function getFilename();

    /**
     * @return User
     */
    public function getOwner();

} 