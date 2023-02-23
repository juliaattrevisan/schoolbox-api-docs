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
                {$this->getJoinColumnString('deletedBy', 'deletedBy')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('createdBy', '', 'createdBy')}
            INNER JOIN {$this->getJoinSql('updatedBy', '', 'updatedBy')}
            LEFT JOIN {$this->getJoinSql('deletedBy', '', 'deletedBy')}
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
            $template = $this->hydrate($row);

            /* Arbitrarily died; this shouldn't happen tho
             */
            if (!$template) {
                continue;
            }

            /* Various effectors
             *
             * Not sure how deletedBy gets used yet; may be superfluous
             * in this contextual usage
             */
            /** @var User $createdBy */
            $createdBy = $this->hydrateJoin($row, 'createdBy', 'createdBy');

            /** @var User $createdBy */
            $updatedBy = $this->hydrateJoin($row, 'updatedBy', 'updatedBy');

            /** @var User $createdBy */
            $deletedBy = $this->hydrateJoin($row, 'deletedBy', 'deletedBy');

            if ($createdBy) {
                $template->setCreatedBy($createdBy);
            }

            if ($updatedBy) {
                $template->setUpdatedBy($updatedBy);
            }

            if ($deletedBy) {
                $template->setDeletedBy($deletedBy);
            }

            $collection->add($template);
        }

        return $collection;
    }
}
