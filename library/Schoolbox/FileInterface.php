<?php
/**
* Standard interface for files, currently the video queue system relies on this to process files
*
* @author    Amri Sannang <amri.sannang@alaress.com.au>
* @copyright Copyright 2013, Alaress Pty Ltd
* @link      http://www.alaress.com.au/
*/
namespace Schoolbox;


// use Schoolbox\Resource\File\IFile;

/**
 * Interface FileInterface
 *
 * @deprecated in favour of Resource\File\IFile, this interface retained for backward compatibility
 * @package Schoolbox
 */
interface FileInterface /* extends IFile */
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

    /**
     * Returns the path to the file on the filesystem
     * @return string
     */
    public function getFileLocation();

    /**
     * Returns the size on disk of the file
     * @return int
     */
    public function getFilesize();

}