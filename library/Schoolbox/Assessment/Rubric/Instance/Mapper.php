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
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;

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
            'capabilityMarkType',
            'capabilityMarkType',
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
            'capabilityGradeGroup',
            'capabilityGradeGroup',
        );
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
                {$this->getJoinColumnString('assessmentModel', 'assessmentModel')},
                {$this->getJoinColumnString('capabilityMarkType', 'capabilityMarkType')},
                {$this->getJoinColumnString('capabilityGradeGroup', 'capabilityGradeGroup')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('assessmentModel', '', 'assessmentModel', true)}
            LEFT JOIN {$this->getJoinSql('capabilityMarkType', '', 'capabilityMarkType', true)}
            LEFT JOIN {$this->getJoinSql('capabilityGradeGroup', '', 'capabilityGradeGroup', true)}
        ";

        return $selectString;
    }


    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param \PDOStatement $statement
     * @return GenericCollection|Model
     * @throws Exception
     * @throws DefinitionException
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(\PDOStatement $statement): Model|GenericCollection
    {
        $stack = $this->createCollection();

        foreach ($statement as $row) {
            $rubric = $this->hydrate($row);

            /* Bolt on the IAssessable instance
             */
            /** @var \Schoolbox\Learning\Assessment\DataModel\Model $assessment */
            $assessment = $this->hydrateJoin($row, 'assessmentModel', 'assessmentModel');
            $rubric->setAssessmentModel($assessment);

            /* Figure out the capability mark type which might be optional
             *
             * Note that the ui coerces users into having selected a Capability Mark Type when enabling rubrics
             * even if that Capability Mark Type is "None" - "None" still fulfils the same programmatic interface as
             * any other numeric mark type (percentage, letter grade, etc)
             *
             * The only real way this would catastrophically fail is when capability mark type was null but that would
             * be because of bad data, not the normal day-to-day usage
             */
            $capabilityMarkType = $this->hydrateJoin($row, 'capabilityMarkType', 'capabilityMarkType');
            $rubric->setCapabilityMarkType($capabilityMarkType);

            /* If it was a Letter Grade, go find its levels
             * A Capability Mark Type instance is always presumed to have existed here, even if that was "None"
             * but I've added a nullsafe operator anyway, because AI doesn't know about this.
             */
            if (SubmissionBox\MarkType::SLUG_LETTER === $rubric->getCapabilityMarkType()?->getSlug()) {
                /** @var SubmissionBox\GradeLevel\Group\Model $capabilityGradeGroup */
                $capabilityGradeGroup = $this->getJoinMapper('capabilityGradeGroup')->findByIdWithFallback($rubric->getCapabilityGradeGroup()?->getId());

                /* This probably doesn't happen, but if the capability grade group wasn't found at all, set as null
                 */
                if (null !== $capabilityGradeGroup->getId()) {
                    $rubric->setCapabilityGradeGroup($capabilityGradeGroup);
                }
            }

            $stack->add($rubric);
        }

        return $stack;
    }
}
