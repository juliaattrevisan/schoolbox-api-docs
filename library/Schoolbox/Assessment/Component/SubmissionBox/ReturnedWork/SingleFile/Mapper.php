<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile;

use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Collection;
use Schoolbox\File\Mimetype;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

/**
 * A mapper for a single file within a return (ie Teacher Feedback)
 *
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    /**
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('submission_return_file');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);

        $this->joins('submission_return_id', ReturnedWorkMapper::class, 'id', null, null, 'return', 'return');
        $this->joins('hash', StorageMapper::class, 'hash', null, null, 'storage', 'storage');
        $this->uses('realname', self::TYPE_STRING, null, 'filename');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('return', 'r')},
                {$this->getJoinColumnString('storage', 'f')},
                {$this->getJoinColumnString('storage.allowedFileExtension', 'm')}
            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('return', '', 'r', true)}
            INNER JOIN {$this->getJoinSql('storage', '', 'f', true)}
            LEFT JOIN {$this->getJoinSql('storage.allowedFileExtension', 'f', 'm', true)}
        ";
    }

    /**
     * Include storage and student's submission.
     *
     * @param PDOStatement $statement
     * @return Collection|Model[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var Collection|Model[] $resultSet */
        $resultSet = $this->createCollection();

        foreach ($statement as $row) {
            $result = $this->hydrate($row);

            /**
             * @var Storage      $storage
             * @var Mimetype     $mime
             * @var ReturnedWork $return
             */
            $storage = $this->hydrateJoin($row, 'storage', 'f');
            $mime    = $this->hydrateJoin($row, 'storage.allowedFileExtension', 'm');
            $return  = $this->hydrateJoin($row, 'return', 'r');

            $resultSet->add(
                $result
                    ->setStorage($storage->setMimetype($mime))
                    ->setReturn($return)
            );
        }
        return $resultSet;
    }
}


