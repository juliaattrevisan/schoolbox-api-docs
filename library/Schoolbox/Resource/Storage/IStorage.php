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

interface IStorage
{

    /**
     * Returns the full mime string for the file
     * @return string
     */
    public function getMime();

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