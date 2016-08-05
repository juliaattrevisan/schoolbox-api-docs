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
        $this->hasTable('course_unit');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('course_id', CourseMapper::getInstance(), 'id', null, null, 'course');
        $this->joins('folder_id', FolderMapper::getInstance(), 'id', null, null, 'folder');
    }

    /**
     * Converts a DeprecatedModel for a course into a new model.
     *
     * @param DeprecatedModel $deprecatedModel
     * @return Model
     */
    public function convertDeprecatedModel(DeprecatedModel $deprecatedModel)
    {
        return $this->createModel()
            ->setFolder($this->getJoinMapper('folder')->findById($deprecatedModel->getId()))
            ->setCourse($this->getJoinMapper('course')->findOneByColumn('folder_id', $deprecatedModel->getParent()->getId()));
    }
}
