<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\SingleFile;

use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Collection;
use Schoolbox\File\Mimetype;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

/**
 * Mapper for files attached to a submission box (due work).
 *
 * @method Collection|Model[] findByColumn($columnName, $columnValue, array $options = [])
 */
class Mapper extends BaseMapper
{
    protected function mapping()
    {
        $this->hasTable('submission_box_file');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);

        $this->joins('box_id', SubmissionBoxMapper::class, 'id', null, null, 'box', 'box');
        $this->joins('storage_id', StorageMapper::class, 'hash', null, null, 'storage', 'storage');
        $this->uses('realname', self::TYPE_STRING, null, 'filename');
    }


    /**
     * @return string
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
     * @return Collection|Model[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var Collection|Model[] $resultSet */
        $resultSet = $this->createCollection();

        foreach ($statement as $row) {
            /** @var Model $result */
            $result = $this->hydrate($row);

            /**
             * @var Storage      $storage
             * @var Mimetype     $mime
             */
            $storage = $this->hydrateJoin($row, 'storage', 'f');
            $mime    = $this->hydrateJoin($row, 'storage.allowedFileExtension', 'm');

            $resultSet[$result->getId()] = $result->setStorage($storage->setMimetype($mime));
        }
        return $resultSet;
    }
}
