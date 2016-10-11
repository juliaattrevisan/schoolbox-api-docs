<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Implementation\Unit;

use Schoolbox\Collection;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for the data model for Unit Implementations.
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
        $this->hasTable('course_unit_implementation');
        $this->hasModel(Model::class);

        // Identifier
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);

        // Course Implementation
        $this->joins('course_implementation_id', CourseImplementationMapper::class, 'id', null, null, 'course', 'course');

        // Unit Specification
        $this->joins('course_unit_specification_id', UnitSpecificationMapper::class, 'id', null, null, 'specification', 'specification');

        // Title
        $this->uses('title', self::TYPE_STRING);

        // Open/close date
        $this->uses('open_date', self::TYPE_DATETIME);
        $this->uses('due_date', self::TYPE_DATETIME);

        // Datestamps
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * Finds all unit implementations for this course implementation.
     *
     * @param int|array $courseId
     * @param array     $options
     * @return Collection|Model[]
     */
    public function findAllByCourseId($courseId, $options = [])
    {
        return $this->findByColumn('course_implementation_id', $courseId, $options);
    }
}
