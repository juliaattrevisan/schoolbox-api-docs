<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Attachment;

use PDOStatement;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;

/**
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_attachment');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('pastoral_id', PastoralMapper::class, 'id', null, null, 'pastoral', 'pastoral');
        $this->joins('hash', StorageMapper::class, 'hash', null, null, 'storage', 'storage');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        $storageMapper = $this->getJoinMapper('storage');

        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('pastoral', 'p')},
                        {$this->getJoinColumnString('storage', 's')},
                        {$storageMapper->getJoinColumnString('allowedFileExtension', 'mime')}
            FROM        {$this->getTable()} 
            INNER JOIN  {$this->getJoinSql('pastoral', '', 'p', true)}
            INNER JOIN  {$this->getJoinSql('storage', '', 's', true)}
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

            /** @var Pastoral $pastoral */
            $pastoral = $this->hydrateJoin($row, 'pastoral', 'p');
            $model->setPastoral($pastoral);

            // Hydrate storage object

            /** @var Storage $storage */
            $storage = $this->hydrateJoin($row, 'storage', 's');

            // Hydrate a mime type if we can find one

            /** @var Mimetype $mime */
            $mime = $mimeMapper->hydrate($row, 'mime');

            if ($mime instanceof Mimetype) {
                $storage->setMimetype($mime);
            }

            $model->setStorage($storage);

            $collection->add($model);
        }

        return $collection;
    }
}
