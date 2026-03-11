<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 * @method GenericCollection<Model> findAll($options = [])
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
        $this->hasTable('reporting_feedback');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('norm_mark', self::TYPE_DECIMAL, null, 'normalisedMark');
        $this->uses('mark', self::TYPE_STRING, null, 'humanReadableMark');
        $this->uses('instructor_mark', self::TYPE_STRING, null, 'humanReadableInstructorMark');
        $this->uses('comment', self::TYPE_STRING);

        $this->joins(
            'reporting_id',
            \Schoolbox\Learning\Assessment\DataModel\Reporting\Mapper::class,
            'id',
            null,
            null,
            'reporting',
            'reporting'
        );

        $this->joins(
            'learner_id',
            UserMapper::class,
            'id',
            null,
            null,
            'learner',
            'learner'
        );

        $this->joins(
            'instructor_id',
            UserMapper::class,
            'id',
            null,
            null,
            'instructor',
            'instructor'
        );


        /* Auditor fields
         */
        $this->uses('published_at', self::TYPE_DATETIME);
        $this->uses('published_by', self::TYPE_INTEGER, null, 'publishedBy');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('created_by', self::TYPE_INTEGER, null, 'createdBy');
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('updated_by', self::TYPE_INTEGER, null, 'updatedBy');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->uses('deleted_by', self::TYPE_INTEGER, null, 'deletedBy');

        /* Created/Updated/Deleted By fields need special treatment due to swampy reasons
         */
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'auditorFields']);
        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'deleterFields']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'restoreDeleterFields']);
    }


    /**
     * @inheritDoc
     *
     * @return string
     */
    protected function getSelectString()
    {
        $select = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('reporting', 'reporting')},
                {$this->getJoinColumnString('learner', 'learner')},
                {$this->getJoinColumnString('instructor', 'instructor')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('reporting', '', 'reporting', true)}
            INNER JOIN {$this->getJoinSql('learner', '', 'learner', true)}
            INNER JOIN {$this->getJoinSql('instructor', '', 'instructor', true)}
        ";

        return $select;
    }


    /**
     * @param \PDOStatement $statement
     * @return GenericCollection
     * @throws Exception
     */
    protected function hydrateSelect(\PDOStatement $statement)
    {
        $stack = $this->createCollection();

        foreach ($statement as $row) {
            /** @var \Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback\Model $feedback */
            $feedback = $stack->offsetGet($row['id']);

            /* First time we've encountered this $feedback; init an instance that we'll reuse later
             */
            if (null === $feedback) {
                /** @var \Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback\Model $feedback */
                $feedback = $this->hydrate($row);

                /** @var \Schoolbox\Learning\Assessment\DataModel\Model $assessmentModel */
                $assessmentModel = $this->createJoinModel('reporting.assessmentModel', $row['reporting.assessment_id']);

                /** @var \Schoolbox\Learning\Assessment\DataModel\Reporting\Model $reporting */
                $reporting = $this->createJoinModel('reporting', $row['reporting.id']);
                $reporting->setAssessmentModel($assessmentModel);

                $feedback
                    ->setReporting($reporting)
                    ->setLearner($this->hydrateJoin($row, 'learner', 'learner'))
                    ->setInstructor($this->hydrateJoin($row, 'instructor', 'instructor'));

                /* Not entirely sure why we set this but it must be a convenient shortcut elsewhere.
                 * There's also a ->setAssessable() here, but nobody seems to use it
                 */
                $feedback->setAssessmentModel($assessmentModel);

                $stack->setOffset($feedback->getId(), $feedback);
            }
        }

        return $stack;
    }



    /**
     * Prefill auditor fields (the Swamp shouldn't need to know about this)
     */
    protected function auditorFields(Model $feedback): void
    {
        /* TODO Technical Uplift Candidate
         *  The traits are wrong and should have accepted a user instance rather than just an id
         *  All usages of the trait require you to ->getId() anyway, which means you have the user instance already.
         *  WHy not just supply that instead?
         */
        if (null === $feedback->getCreatedBy()) {
            $feedback->setCreatedBy($this->getSessionUser()->getId());
        }

        if (null === $feedback->getUpdatedBy()) {
            $feedback->setUpdatedBy($this->getSessionUser()->getId());
        }
    }


    /**
     * Prefill deleter fields (the Swamp shouldn't need to know about this)
     *
     * @param Model $feedback
     * @return void
     * @throws Exception
     */
    protected function deleterFields(Model $feedback): void
    {
        /* Remember who deleted this thing, in a dirty doubletap
         */
        $feedback->setDeletedBy($this->getSessionUser()->getId());
        $this->save($feedback);
    }


    /**
     * Forget who deleted this. Used when undeleting
     *
     * @param Model $feedback
     * @return void
     * @throws Exception
     */
    public function restoreDeleterFields(Model $feedback): void
    {
        /* Forget who deleted this thing, in a dirty doubletap
         */
        $feedback->setDeletedBy(null);
        $this->save($feedback);
    }
}
