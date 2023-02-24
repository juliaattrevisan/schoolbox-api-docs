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
            INNER JOIN {$this->getJoinSql('createdBy', '', 'createdBy')}
            INNER JOIN {$this->getJoinSql('updatedBy', '', 'updatedBy')}
            LEFT JOIN {$this->getJoinSql('deletedBy', '', 'deletedBy')}
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
}
