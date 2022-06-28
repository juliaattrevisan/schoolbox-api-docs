<?php

/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan;

use PDOStatement;
use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Model as Plagscan;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as Storage;

/**
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * Mapping.
     */
    protected function mapping(): void
    {
        $this->hasTable('plagscan_document');
        $this->hasModel(Plagscan::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('student_submission_file_id', self::TYPE_INTEGER);
        $this->uses('plagscan_id', self::TYPE_INTEGER);
        $this->uses('status', self::TYPE_INTEGER);
        $this->uses('score', self::TYPE_STRING);
        $this->joins('storage_hash', StorageMapper::class, 'hash', null, null, 'storage', 'storage');
        $this->uses('interactive_url', self::TYPE_STRING);
        $this->uses('privatised_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString(): string
    {
        $storageMapper = $this->getJoinMapper('storage');

        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('storage', 's')},
                        {$storageMapper->getJoinColumnString('allowedFileExtension', 'mime')}
            FROM        {$this->getTable()}
            LEFT JOIN   {$this->getJoinSql('storage', '', 's', true)}
            LEFT JOIN   {$storageMapper->getJoinSql('allowedFileExtension', 's', 'mime')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        // Return attachments fully hydrated by default
        $collection = $this->createCollection();

        /** @var MimetypeMapper $mimeMapper */
        $mimeMapper = $this->getJoinMapper('storage')->getJoinMapper('allowedFileExtension');

        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            // Hydrate storage object
            /** @var Storage $storage */
            $storage = $this->hydrateJoin($row, 'storage', 's');

            if ($storage instanceof Storage) {
                // Hydrate a mime type if we can find one
                /** @var Mimetype $mime */
                $mime = $mimeMapper->hydrate($row, 'mime');

                if ($mime instanceof Mimetype) {
                    $storage->setMimetype($mime);
                }
                $model->setStorage($storage);
            }
            $collection->add($model);
        }

        return $collection;
    }
}
