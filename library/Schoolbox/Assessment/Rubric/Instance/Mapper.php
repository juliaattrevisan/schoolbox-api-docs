<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Instance;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;

/**
 * Class Mapper
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method null|Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model[]|GenericCollection findAll($contents = [])
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method Model[]|GenericCollection createCollection($contents = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('rubric_instance');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('rubric_mark_range', self::TYPE_BOOLEAN, null, 'markedUsingRange');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joins(
            'assessment_id',
            AssessmentMapper::class,
            'id',
            null,
            null,
            'assessmentModel',
            'assessmentModel',
        );

        /* Mark Types are currently living in SubmissionBox folder, despite being a shared concept across all the
         * learning activities; Amri and I have discussed its future being promoted to a more global space later
         */
        $this->joins(
            'rubric_capability_mark_type_id',
            SubmissionBox\MarkTypeMapper::class,
            'mark_type_id',
            null,
            null,
            'capabilityMarkType'
        );

        /* Grade Groups are currently living in SubmissionBox folder, despite being a shared concept across all the
         * learning activities; Amri and I have discussed its future being promoted to a more global space later
         */
        $this->joins(
            'rubric_capability_grade_group_id',
            SubmissionBox\GradeLevel\Group\Mapper::class,
            'id',
            null,
            null,
            'capabilityGradeGroup'
        );
    }
}
