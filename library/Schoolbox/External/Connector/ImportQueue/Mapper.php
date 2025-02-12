<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\ImportQueue;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model[]|GenericCollection findAll($contents = [])
 * @method Model hydrate(array $row, $alias = '', $callback = null)
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
        $this->hasTable('sis_import_queue');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('strats', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
    }


    /**
     * @inheritDoc
     *
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('user', 'u')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('user', '', 'u', true)}
        ";
    }


    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param \PDOStatement $statement
     * @return GenericCollection
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(\PDOStatement $statement): GenericCollection
    {
        $collection = $this->createCollection();

        foreach ($statement as $row) {
            $record = $this->hydrate($row);

            /* Hydrate the user that requested this import queue record
             */
            /** @var \Schoolbox\User $user */
            $user = $this->hydrateJoin($row, 'user', 'u');

            $record->setUser($user);

            $collection->add($record);
        }

        return $collection;
    }
}
