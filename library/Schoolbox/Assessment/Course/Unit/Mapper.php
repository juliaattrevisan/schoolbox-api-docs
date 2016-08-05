<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Unit;

use Schoolbox\Assessment\Course\Mapper as CourseMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

/**
 * Class Mapper
 *
 * @package Schoolbox\Assessment\Course\Unit
 */
class Mapper extends BaseMapper
{
    protected function mapping()
    {
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('course_id', CourseMapper::getInstance(), 'id', null, null, 'course');
        $this->joins('folder_id', FolderMapper::getInstance(), 'id', null, null, 'folder');
    }
}
