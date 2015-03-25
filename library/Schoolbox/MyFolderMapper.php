<?php
/**
 * Mapper for the folders in My Files.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;
use Schoolbox\Resource\FolderLocatorInterface;

/**
 * Class MyFolderMapper
 *
 * @deprecated in favour of Resource\My\Folder\Mapper, this class retained for backward compatibility
 * @package Schoolbox
 */
class MyFolderMapper extends Resource\My\Folder\Mapper implements FolderLocatorInterface
{
    // bc.
}
