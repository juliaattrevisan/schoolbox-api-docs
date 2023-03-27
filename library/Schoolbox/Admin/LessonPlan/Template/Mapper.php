<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\LessonPlan\Template;

use PDOStatement;
use Schoolbox\GenericCollection;
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
        $this->hasTable('lesson_plan_template');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('enabled', self::TYPE_BOOLEAN);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'createdBy', 'createdBy');
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->joins('updated_by', UserMapper::class, 'id', null, null, 'updatedBy', 'updatedBy');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joins('deleted_by', UserMapper::class, 'id', null, null, 'deletedBy', 'deletedBy');

        $this->hasJoin(
            'field',
            [
                'mapper'    => Field\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'lesson_plan_template_id',
                'multiple'  => true,
            ]
        );

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
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('createdBy', 'createdBy')},
                {$this->getJoinColumnString('updatedBy', 'updatedBy')},
                {$this->getJoinColumnString('deletedBy', 'deletedBy')},
                {$this->getJoinColumnString('field', 'field')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('createdBy', '', 'createdBy', true)}
            INNER JOIN {$this->getJoinSql('updatedBy', '', 'updatedBy', true)}
            LEFT JOIN {$this->getJoinSql('deletedBy', '', 'deletedBy', true)}
            LEFT JOIN {$this->getJoinSql('field', '', 'field')}
        ";
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
        $fieldMapper = $this->getJoinMapper('field');

        /** @var Model[] $templates */
        $templates = [];

        foreach ($statement as $row) {
            if (!isset($templates[$row['id']])) {
                /** @var Model|null $template */
                $template = $this->hydrate($row);
                $template->setFields($fieldMapper->createCollection());

                $templates[$row['id']] = $template;
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
                $templates[$row['id']]->setCreatedBy($createdBy);
            }

            if ($updatedBy) {
                $templates[$row['id']]->setUpdatedBy($updatedBy);
            }

            if ($deletedBy) {
                $templates[$row['id']]->setDeletedBy($deletedBy);
            }

            /* Stack up all the adjoining fields
             */
            $field = $fieldMapper->hydrate($row, 'field');

            if ($field instanceof Field\Model) {
                $templates[$row['id']]->addField($field);
            }
        }

        /* Sort sequentially
         */
        foreach ($templates as $template) {
            $template->getFields()->sortBy('sequence', 'asc');
        }

        return $this->createCollection(array_values($templates));
    }


    /**
     * Find the most recent unique Lesson Plan Templates' ids from which
     * the $user spawned Lesson Plans. This can be used to find the fully
     * hydrated LPTs themselves via the LPT mapper.
     *
     * @param User $user
     * @param int  $limit
     * @return GenericCollection
     * @throws \PDOException
     */
    public function findRecentlyUsed(\Schoolbox\User $user, int $limit = 3): GenericCollection
    {
        $params = [
            'creatorId' => $user->getId(),
            'enabled' => true,
        ];

        /* For the given user,
         *
         * Find all the Lesson Plans they created, ordered by created_at,
         * along with all the Lesson Plan Templates used to create them.
         *
         * NOTE: Only care about enabled templates; disabled templates
         * shouldn't appear in any list anywhere, I guess
         *
         * Pick the top three unique Lesson Plan Templates and discard
         * the rest.
         *
         * Make a unique query to do that, I guess, to save some hydration
         *
         * Note: this is a groupwise maximum.
         */
        $query = "
            SELECT q1.id
            FROM (
                SELECT 
                a1.created_at,
                lpt1.id
                FROM lesson_plan lp1
                INNER JOIN assessment a1 ON lp1.assessment_id = a1.id
                INNER JOIN {$this->getTable()} lpt1 ON lp1.lesson_plan_template_id = lpt1.id
                WHERE lp1.created_by = :creatorId
                AND lpt1.enabled = :enabled
                ORDER BY a1.created_at DESC
            ) q1 LEFT JOIN (
                SELECT 
                a2.created_at,
                lpt2.id
                FROM lesson_plan lp2
                INNER JOIN assessment a2 ON lp2.assessment_id = a2.id
                INNER JOIN {$this->getTable()} lpt2 ON lp2.lesson_plan_template_id = lpt2.id
                WHERE lp2.created_by = :creatorId
                AND lpt2.enabled = :enabled
                ORDER BY a2.created_at DESC
            ) q2 ON q1.id = q2.id AND q1.created_at < q2.created_at
            WHERE q2.id is null
            ORDER BY q1.created_at DESC 
            LIMIT :limit
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);

        foreach ($params as $label => $value) {
            $statement->bindValue($label, $value);
        }

        $statement->execute();

        $ids = [];

        while ($row = $statement->fetchColumn()) {
            $ids[] = $row;
        }

        return $this->findByColumn('id', $ids)->sortNaturally('title', 'asc');
    }


    /**
     * Find all the Lesson Plan Templates that are actually enabled
     *
     * @return GenericCollection
     * @throws \InvalidArgumentException
     */
    public function findEnabled(): GenericCollection
    {
        return $this->findByColumn('enabled', true)->sortNaturally('title', 'asc');
    }


    /**
     * Delete the fields that were associated with $lessonPlanTemplate
     *
     * @param Model $lessonPlanTemplate
     * @return void
     * @throws DefinitionException
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    protected function softDeleteFields(Model $lessonPlanTemplate): void
    {
        /* Remember who deleted this thing, in a dirty doubletap
         */
        $lessonPlanTemplate->setDeletedBy($this->getSessionUser());
        $this->save($lessonPlanTemplate);

        /* Dump away any associated Fields too
         */
        $this->getJoinMapper('field')->delete($lessonPlanTemplate->getFields());
    }
}
