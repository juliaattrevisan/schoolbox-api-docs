<?php
/**
* Standard interface for files, currently the video queue system relies on this to process files
*
* @author    Amri Sannang <amri.sannang@alaress.com.au>
* @copyright Copyright 2013, Alaress Pty Ltd
* @link      http://www.alaress.com.au/
*/
namespace Schoolbox;


use Schoolbox\Resource\File\IFile;

/**
 * Interface FileInterface
 *
 * @deprecated
 * @package Schoolbox
 */
interface FileInterface extends IFile
{

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