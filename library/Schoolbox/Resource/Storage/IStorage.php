<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Storage;

use Alaress_Date_Format as ADF;
use Schoolbox\File\Mimetype;

/**
 * Interface IStorage
 *
 * Common methods for basic file-based functionality, extended by IFile
 *
 * @package Schoolbox\Resource\Storage
 */
interface IStorage
{

    /**
     * Returns the real filename of the file
     * @return string
     */
    public function getFilename();

    /**
     * Returns the full mime string for the file
     * @return string
     */
    public function getMime();

    /**
     * Returns the full mime string for the file
     * @return Mimetype
     */
    public function getMimetype();

    /**
     * Returns the size on disk of the file
     * @return int
     */
    public function getSize();

    /**
     * @return string
     */
    public function getHash();

    /**
     * @return ADF
     */
    public function getCreatedAt();

    /**
     * @return ADF
     */
    public function getLastAccessed();

} 