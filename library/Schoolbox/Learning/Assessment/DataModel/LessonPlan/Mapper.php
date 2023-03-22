<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\LessonPlan;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;
use Schoolbox\UserMapper;

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
        $this->hasTable('lesson_plan');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('lesson_plan_template_id', \Schoolbox\Admin\LessonPlan\Template\Mapper::class, 'id', null, null, 'lessonPlanTemplate', 'lessonPlanTemplate');
        $this->uses('visibility', self::TYPE_STRING);
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'createdBy', 'createdBy');
        $this->joins('updated_by', UserMapper::class, 'id', null, null, 'updatedBy', 'updatedBy');
        $this->joins('deleted_by', UserMapper::class, 'id', null, null, 'deletedBy', 'deletedBy');

        // This column is duplicate of assessment.deleted_at, but is required to ensure soft-deletion on this table.
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->hasJoin(
            'lessonPlanField',
            [
                'mapper'    => Field\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'lesson_plan_id',
                'multiple'  => true,
            ]
        );

        $this->joins(
            'assessment_id',
            AssessmentMapper::class,
            'id',
            null,
            null,
            'assessmentModel',
            'assessmentModel',
        );

        $this->hasJoin(
            'files',
            [
                'multiple'  => true,
                'mapper'    => File\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'lesson_plan_id',
            ]
        );

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'auditorFields']);
        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'softDeleteFields']);
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
                {$this->getJoinColumnString('createdBy', 'createdBy')},
                {$this->getJoinColumnString('updatedBy', 'updatedBy')},
                {$this->getJoinColumnString('deletedBy', 'deletedBy')},
                {$this->getJoinColumnString('lessonPlanTemplate', 'lessonPlanTemplate')},
                {$this->getJoinColumnString('lessonPlanField', 'lessonPlanField')},
                {$this->getJoinColumnString('lessonPlanField.lessonPlanTemplateField', 'lessonPlanTemplateField')},
                {$this->getJoinColumnString('assessmentModel', 'assessmentModel')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('assessmentModel', '', 'assessmentModel')}
            INNER JOIN {$this->getJoinSql('lessonPlanTemplate', '', 'lessonPlanTemplate', true)} 
            INNER JOIN {$this->getJoinSql('createdBy', '', 'createdBy', true)}
            INNER JOIN {$this->getJoinSql('updatedBy', '', 'updatedBy', true)}
            LEFT JOIN {$this->getJoinSql('deletedBy', '', 'deletedBy', true)}
            LEFT JOIN {$this->getJoinSql('lessonPlanField', '', 'lessonPlanField')}
            LEFT JOIN {$this->getJoinSql('lessonPlanField.lessonPlanTemplateField', 'lessonPlanField', 'lessonPlanTemplateField')}
        ";

        return $selectString;
    }


    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param PDOStatement $statement
     * @return GenericCollection|Model
     * @throws Exception
     * @throws DefinitionException
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(PDOStatement $statement): Model|GenericCollection
    {
        $fieldMapper = $this->getJoinMapper('lessonPlanField');
        $templateMapper = $this->getJoinMapper('lessonPlanTemplate');
        $templateFieldMapper = $this->getJoinMapper('lessonPlanField.lessonPlanTemplateField');

        /** @var Model[] $lessonPlans */
        $lessonPlans = [];

        foreach ($statement as $row) {
            if (!isset($lessonPlans[$row['id']])) {
                /** @var Model|null $lessonPlan */
                $lessonPlan = $this->hydrate($row);
                $lessonPlan->setFields($fieldMapper->createCollection());

                /* Prepare its template as well
                 */
                /** @var \Schoolbox\Admin\LessonPlan\Template\Model|null $template */
                $template = $templateMapper->hydrate($row, 'lessonPlanTemplate', 'lessonPlanTemplate');
                $template->setFields($templateFieldMapper->createCollection());

                /* Slap in a Template that's ready to receive Template Fields
                 */
                $lessonPlan->setLessonPlanTemplate($template);

                /* Bolt on the IAssessable instance
                 */
                $assessment =  $this->hydrateJoin($row, 'assessmentModel', 'assessmentModel');
                $lessonPlan->setAssessmentModel($assessment);

                /* Stack it up
                 */
                $lessonPlans[$row['id']] = $lessonPlan;
            }

            /* Various effectors
             *
             * Not sure how deletedBy gets used yet; may be superfluous
             * in this contextual usage
             */
            /** @var User|null $createdBy */
            $createdBy = $this->hydrateJoin($row, 'createdBy', 'createdBy');

            /** @var User|null $updatedBy */
            $updatedBy = $this->hydrateJoin($row, 'updatedBy', 'updatedBy');

            /** @var User|null $deletedBy */
            $deletedBy = $this->hydrateJoin($row, 'deletedBy', 'deletedBy');

            if ($createdBy) {
                $lessonPlans[$row['id']]->setCreatedBy($createdBy);
            }

            if ($updatedBy) {
                $lessonPlans[$row['id']]->setUpdatedBy($updatedBy);
            }

            if ($deletedBy) {
                $lessonPlans[$row['id']]->setDeletedBy($deletedBy);
            }

            /* Stack up all the adjoining fields
             */
            $lessonPlanField = $fieldMapper->hydrate($row, 'lessonPlanField');

            if ($lessonPlanField instanceof Field\Model) {
                $lessonPlans[$row['id']]->addField($lessonPlanField);

                /* Hydrate and supply the original Lesson Plan Template Field
                 */
                /** @var \Schoolbox\Admin\LessonPlan\Template\Field\Model $lessonPlanTemplateField */
                $lessonPlanTemplateField = $templateFieldMapper->hydrate($row, 'lessonPlanTemplateField', 'lessonPlanTemplateField');
                $lessonPlanField->setLessonPlanTemplateField($lessonPlanTemplateField);

                /* Reference it in the Lesson Plan Template Fields as well
                 */
                $lessonPlans[$row['id']]->getLessonPlanTemplate()->addField($lessonPlanTemplateField);
            }

            /* TODO: Augment with attached files
             */
            // $lessonPlans[$row['id']]->setAttachments($this->getJoinMapper('files')->findByColumn('lesson_plan_id', $lessonPlans[$row['id']]->getId()));
        }

        /* Sort sequentially
         */
        foreach ($lessonPlans as $lessonPlan) {
            $lessonPlan->getFields()->sortBy('sequence', 'asc');
        }

        return $this->createCollection(array_values($lessonPlans));
    }


    /**
     * Persist Lesson Plan attachments/files.
     *
     * @param Model $lessonPlan
     */
    public function saveFiles(Model $lessonPlan): void
    {
        $this->cascadeJoin('files', ['lesson_plan_id' => $lessonPlan->getId()], $lessonPlan->getAttachments());
    }


    /**
     * Persist Lesson Plan Fields.
     *
     * @param Model $lessonPlan
     */
    public function saveFields(Model $lessonPlan): void
    {
        $this->cascadeJoin('lessonPlanField', ['lesson_plan_id' => $lessonPlan->getId()], $lessonPlan->getFields());
    }


    /**
     * Delete the fields that were associated with $lessonPlanTemplate
     *
     * @param Model $lessonPlan
     * @return void
     * @throws DefinitionException
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    protected function softDeleteFields(Model $lessonPlan): void
    {
        /* Remember who deleted this thing, in a dirty doubletap
         */
        $lessonPlan->setDeletedBy($this->getSessionUser());
        $this->save($lessonPlan);

        /* Dump away any associated Fields too
         */
        $this->getJoinMapper('lessonPlanField')->delete($lessonPlan->getFields());
    }


    /**
     * Prefill auditor fields (the Swamp shouldn't need to know about this)
     */
    protected function auditorFields(Model $lessonPlan): void
    {
        if (null === $lessonPlan->getCreatedBy()) {
            $lessonPlan->setCreatedBy($this->getSessionUser());
        }

        if (null === $lessonPlan->getUpdatedBy()) {
            $lessonPlan->setUpdatedBy($this->getSessionUser());
        }
    }
}
