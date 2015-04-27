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
     * Returns the first part of the filename
     * @return string
     */
    public function getFilename();

    /**
     * Returns just the extension of the file
     * @return string
     */
    public function getExtension();

    /**
     * Returns the real filename of the file
     * @return string
     */
    public function getBasename();

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

    /**
     * Check whether the file exists.
     *
     * @inherited from Flysystem\File
     * @return bool
     */
    public function exists();

    /**
     * Read the file.
     *
     * @inherited from Flysystem\File
     * @return string file contents
     */
    public function read();

    /**
     * Read the file as a stream.
     *
     * @inherited from Flysystem\File
     * @return resource file stream
     */
    public function readStream();

} 