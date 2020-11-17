<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Category;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception\CallbackException;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 *
 * @method Model findById($id)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 * @method Model[] findByColumnList($columnList, array $options = [])
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($idValue = null)
 *
 * @package Schoolbox\Learning\Badge\Category
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('badge_category');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('Sequence', self::TYPE_INTEGER);
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'createdBy', 'createdBy');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'validateName']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'setSequenceIfUnset']);
    }


    /**
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('createdBy', 'c')},
                {$this->getJoinColumnString('createdBy.role', 'cr')}
            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('createdBy', '', 'c', true)}
            INNER JOIN {$this->getJoinSql('createdBy.role', 'c', 'cr')}
        ";
    }


    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param PDOStatement $statement
     * @return GenericCollection|Model[]
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            /* Set the creator and also their rolw
             */
            $createdBy = $this->hydrateJoin($row, 'createdBy', 'c');

            if ($createdBy instanceof User) {
                $role = $this->hydrateJoin($row, 'createdBy.role', 'cr');

                if ($role instanceof Role) {
                    $createdBy->setRole($role);
                }

                $model->setCreatedBy($createdBy);
            }

            $collection->add($model);
        }

        return $collection;
    }


    /**
     * Validates that the name is not empty and is unique
     *
     * @param Model $model
     * @return bool
     * @throws CallbackException
     */
    protected function validateName(Model $model): bool
    {
        if ('' === trim($model->getName() ?? '')) {
            throw new CallbackException("Achievement Category name cannot be empty");
        }

        $query = "
            SELECT id, name
            FROM {$this->getTable()}
            WHERE deleted_at IS NULL
            AND name = :name
        ";

        $params = [
            'name' => $model->getName()
        ];

        /* Updating an existing model shouldn't have its own name count toward collisions
         * i.e. a model shouldn't collide with itself
         */
        if ($model->getId()) {
            $query .= '
                AND id != :id
            ';

            $params['id'] = $model->getId();
        }

        $collisionRows = $this->getDatabase()->query($query, $params)->fetchAll(\PDO::FETCH_ASSOC);

        if (count($collisionRows)) {
            $collisions = array_map(static function (array $row) {
                return "id: {$row['id']}, name: '{$row['name']}'";
            }, $collisionRows);

            throw new CallbackException("Achievement Category names must be unique. Collisions found: " . implode(', ', $collisions));
        }

        return true;
    }


    /**
     * Sets the model's sequence to the next available value if not already set
     *
     * @param Model $model
     * @return bool
     */
    protected function setSequenceIfUnset(Model $model): bool
    {
        if (null === $model->getSequence()) {
            $query = "
                SELECT MAX(sequence)
                FROM {$this->getTable()}
                WHERE deleted_at IS NULL
            ";

            $maxSequence = $this->getDatabase()->query($query)->fetchColumn();
            $nextSequence = $maxSequence !== null ? $maxSequence + 1 : $model::sequenceBase();

            $model->setSequence($nextSequence);
        }

        return true;
    }


    /**
     * @inheritDoc
     */
    protected function getDefaultOrderByParams(): array
    {
        return ['sequence' => 'ASC'];
    }
}
