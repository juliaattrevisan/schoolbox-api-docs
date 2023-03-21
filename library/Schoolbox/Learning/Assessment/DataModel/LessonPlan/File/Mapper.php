<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\LessonPlan\File;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\File\Mimetype;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as Storage;

/**
 *  Mapper for files attached to a task.
 *
 * @method Model createModel($idValue = null)
 * @method GenericCollection|Model[] findAll($options = [])
 * @method Model findById($identifierValue)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 */
class Mapper extends BaseMapper
{
    /**
     * @throws \InvalidArgumentException
     */
    protected function mapping()
    {
        $this->hasTable('lesson_plan_file');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);

        $this->joins('lesson_plan_id', \Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper::class, 'id', null, null, 'lessonPlan', 'lessonPlan');
        $this->joins('storage_hash', StorageMapper::class, 'hash', null, null, 'storage', 'storage');
        $this->uses('realname', self::TYPE_STRING, null, 'filename');
        $this->uses('created_at', self::TYPE_DATETIME);
        $this->uses('updated_at', self::TYPE_DATETIME);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * @return string
     * @throws DefinitionException
     */
    protected function getSelectString()
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
     * @param PDOStatement $statement
     * @return Collection|Model
     * @throws Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var Collection|Model[] $resultSet */
        $resultSet = $this->createCollection();

        foreach ($statement as $row) {
            /** @var Model $result */
            $result = $this->hydrate($row);

            /** @var Storage $storage */
            $storage = $this->hydrateJoin($row, 'storage', 'f');

            /** @var Mimetype $mime */
            $mime    = $this->hydrateJoin($row, 'storage.allowedFileExtension', 'm');

            $resultSet[$result->getId()] = $result->setStorage($storage->setMimetype($mime));
        }
        return $resultSet;
    }
}
