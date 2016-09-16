<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification\Unit;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

/**
 * Mapper for the data model for Unit Specifications.
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
        $this->hasTable('course_unit_specification');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('course_specification_id', CourseSpecificationMapper::class, 'id', null, null, 'course', 'course');
        $this->joins('folder_id', FolderMapper::class, 'id', null, null, null, 'folder');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * Folders and course spec are hydrated by default.
     *
     * @return string
     */
    public function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('folder', 'f')},
                {$this->getJoinColumnString('course', 'c')},
                {$this->getJoinColumnString('course.folder', 'cf')}
            FROM {$this->database->quoteIdentifier($this->table)}
            INNER JOIN {$this->getJoinSql('folder', '', 'f')}
            INNER JOIN {$this->getJoinSql('course', '', 'c')}
            INNER JOIN {$this->getJoinSql('course.folder', 'c', 'cf')}
        ";
    }

    /**
     * Hydrate joined folders
     *
     * @param PDOStatement $statement
     * @return Collection|Model[]
     */
    public function hydrateSelect(PDOStatement $statement)
    {
        $modelList = $this->createCollection();
        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);
            $model->setFolder($this->hydrateJoin($row, 'folder', 'f'));
            $course = $this->hydrateJoin($row, 'course', 'c');
            $course->setFolder($this->hydrateJoin($row, 'course.folder', 'cf'));
            $model->setCourse($course);
            $modelList->add($model);
        }
        return $modelList;
    }
}
