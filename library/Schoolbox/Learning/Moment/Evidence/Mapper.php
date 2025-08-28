<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use Schoolbox\File\Mimetype;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Storage;

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
        $this->hasTable('moment_evidence');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('moment_id', \Schoolbox\Learning\Moment\Mapper::class, 'id', null, null, 'moment', 'moment');
        $this->joins('hash', \Schoolbox\Resource\Storage\Mapper::class, 'hash', null, null, 'storage', 'storage');
        $this->uses('filename', self::TYPE_STRING, null, 'filename');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
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
                {$this->getJoinColumnString('storage', 'f')},
                {$this->getJoinColumnString('storage.allowedFileExtension', 'm')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('storage', '', 'f', true)}
            LEFT JOIN {$this->getJoinSql('storage.allowedFileExtension', 'f', 'm', true)}
        ";
    }


    /**
     * Include storage object in selected result.
     *
     * @param \PDOStatement $statement
     * @return GenericCollection
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function hydrateSelect(\PDOStatement $statement): GenericCollection
    {
        /** @var GenericCollection|Model[] $resultSet */
        $resultSet = $this->createCollection();

        foreach ($statement as $row) {
            /** @var Model $result */
            $result = $this->hydrate($row);

            /** @var Storage\Model $storage */
            $storage = $this->hydrateJoin($row, 'storage', 'f');

            /** @var Mimetype $mime */
            $mime    = $this->hydrateJoin($row, 'storage.allowedFileExtension', 'm');

            $resultSet[$result->getId()] = $result->setStorage($storage->setMimetype($mime));
        }

        /* Sort arbitrarily by created_at in ascending order: oldest file first.
         */
        return $resultSet->sortBy('createdAt', 'asc');
    }
}
