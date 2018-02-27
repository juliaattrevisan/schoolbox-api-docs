<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Course;

use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\Resource\Browser\Resources\TreeBuilder;


class CourseTreeBuilder extends TreeBuilder
{
    /**
     * Class specific filter for which folders appear in the tree
     *
     * @param IFolder $folder
     * @return bool
     */
    protected function filterFolder(IFolder $folder) {

         if($folder->getFolderType() == 'course' || $folder->getFolderType() == 'unit') {
             return false;
         }

         return true;
    }
}
