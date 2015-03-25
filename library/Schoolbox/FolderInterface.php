<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox;


use Schoolbox\Resource\Folder\IFolder;

/**
 * Interface FolderInterface
 *
 * @deprecated in favour of Resource\Folder\IFolder, this interface retained for backward compatibility
 * @package Schoolbox
 */
interface FolderInterface extends IFolder
{
    // BC.
}