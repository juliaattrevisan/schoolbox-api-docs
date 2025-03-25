<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\LessonPlan\Field;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanMapper;
use Schoolbox\Mapper\Exception;

/**
 * @method Model createModel($idValue = null)
 * @method GenericCollection|Model[] findAll($options = [])
 * @method Model findById($identifierValue)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('lesson_plan_field');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('lesson_plan_id', LessonPlanMapper::class, 'id', null, null, 'lessonPlan', 'lessonPlan');
        $this->uses('lesson_plan_template_field_id', self::TYPE_INTEGER, null, 'lessonPlanTemplateField');
        $this->uses('title', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);
        $this->uses('visibility', self::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * {@inheritdoc}
     *
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        $selectString = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('lessonPlan', 'lessonPlan')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('lessonPlan', '', 'lessonPlan', true)}
        ";

        return $selectString;
    }


    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param PDOStatement $statement
     * @return GenericCollection|Model
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(PDOStatement $statement): Model|GenericCollection
    {
        $collection = $this->createCollection();

        foreach ($statement as $row) {
            /** @var Model|null $field */
            $field = $this->hydrate($row);

            /* Arbitrarily died; this shouldn't happen tho
             */
            if (!$field) {
                continue;
            }

            /* Lesson Plan parent object
             *
             * TODO: Work out how much detail you actually need here
             *  since some of it is shared from the AssessmentModel
             */
            $lessonPlan = $this->hydrateJoin($row, 'lessonPlan', 'lessonPlan');
            $field->setLessonPlan($lessonPlan);

            $collection->add($field);
        }

        return $collection;
    }
}
