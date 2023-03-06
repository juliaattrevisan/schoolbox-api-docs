<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\LessonPlan\Field;

use PDOStatement;
use Schoolbox\Admin\LessonPlan\Template\Field\Mapper as LessonPlanTemplateFieldMapper;
use Schoolbox\Admin\LessonPlan\Template\Model as LessonPlanTemplate;
use Schoolbox\GenericCollection;
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
        $this->joins('lesson_plan_template_field_id', LessonPlanTemplateFieldMapper::class, 'id', null, null, 'lessonPlanTemplateField', 'lessonPlanTemplateField');
        $this->uses('body', self::TYPE_STRING);
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
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('lessonPlanTemplateField', 'lessonPlanTemplateField')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('lessonPlanTemplateField', '', 'lessonPlanTemplateField')}
        ";
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
            $field = $this->hydrate($row);

            /* Arbitrarily died; this shouldn't happen tho
             */
            if (!$field) {
                continue;
            }

            /* Lesson Plan Template parent object
             */
            /** @var LessonPlanTemplate|null $template */
            $template = $this->hydrateJoin($row, 'lessonPlanTemplate', 'lpt');

            if ($template) {
                $field->setLessonPlanTemplate($template);
            }

            $collection->add($field);
        }

        return $collection;
    }
}
