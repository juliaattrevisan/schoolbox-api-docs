<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

/**
 * Mapper for the data model for Course Specifications.
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 */
class Mapper extends BaseMapper
{
    /**
     * Setup data source, model and data mappings.
     */
    protected function mapping()
    {
        $this->hasTable('course_specification');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('folder_id', FolderMapper::class, 'id', null, null, null, 'folder');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }
}
