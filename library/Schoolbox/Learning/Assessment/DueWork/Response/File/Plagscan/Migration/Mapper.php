<?php

/**
 * Plagscan document migrator
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Migration;

use PDOStatement;
use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Mapper as PlagscanDocumentMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as Storage;

class Mapper extends BaseMapper
{
    /**
     * Mapping.
     */
    protected function mapping()
    {
        $this->hasTable('plagscan_document_migration');
        $this->hasModel(Model::class);
        $this->joins('plagscan_document_record_id', PlagscanDocumentMapper::class, 'id', null, self::COLUMN_IDENTIFIER, 'plagscanDocument', 'plagscanDocument');
        $this->joins('storage_hash', StorageMapper::class, 'hash', null, null, 'storage', 'storage');
        $this->uses('storage_migrated_at', self::TYPE_DATETIME);
        $this->uses('storage_failed_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE, 'deletedAt');
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        $storageMapper = $this->getJoinMapper('storage');

        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('plagscanDocument', 'p')},
                        {$this->getJoinColumnString('storage', 's')}
            FROM        {$this->getTable()} 
            JOIN        {$this->getJoinSql('plagscanDocument', '', 'p', true)}
            LEFT JOIN   {$this->getJoinSql('storage', '', 's', true)}
            LEFT JOIN   {$storageMapper->getJoinSql('allowedFileExtension', 's', 'mime')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
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
