<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Attachment;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Pastoral\UploadQueue\Mapper as PastoralUploadQueueMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as Storage;

/**
 * Class Mapper
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = [])
 *
 * @package Schoolbox\Pastoral\Attachment
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
        $this->uses('private', self::TYPE_BOOLEAN);
        $this->uses('external_id', self::TYPE_INTEGER);
        $this->joins('pastoral_id', PastoralMapper::class, 'id', null, null, 'pastoral', 'pastoral');
        $this->joins('hash', StorageMapper::class, 'hash', null, null, 'storage', 'storage');
        $this->uses('realname', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'syncToPastoralUploadQueue']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'postDelete']);
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

    /**
     * Post-save hook; sync this to the pastoral upload queue if it's a new file.
     *
     * @param Model      $model
     * @param Model|null $previous
     * @return bool True if model was added to queue, false otherwise.
     */
    public function syncToPastoralUploadQueue(Model $model, Model $previous = null): bool
    {
        // TODO move this restriction to the pastoral upload Q
        if (
            !$previous instanceof Model
            && $this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_EXTERNAL_UPLOAD)
        ) {
            // Queue file for upload to Synergetic DocMan
            // TODO don't getInstance, instead inject
            $pastoralUploadQueueMapper = PastoralUploadQueueMapper::getInstance();
            $queue = $pastoralUploadQueueMapper->createModel();
            $queue->setPastoral($model->getPastoral());
            $queue->setExternalId($model->getPastoral()->getOwner()->getExternalId());
            $queue->setStudentExternalId($model->getPastoral()->getStudent()->getExternalId());
            $queue->setStorage($model->getStorage());
            $queue->setRealname($model->getRealname());
            $queue->setStatus(PastoralUploadQueueMapper::UPLOAD_STATUS_PENDING);
            $queue->setPastoralAttachmentId($model->getId());
            $pastoralUploadQueueMapper->save($queue);

            return true;
        }

        return false;
    }

    /**
     * Delete corresponding pastoral uploads
     * @param Model $object
     * @throws MapperException
     */
    public function postDelete(Model $object)
    {
        $uploadMapper = PastoralUploadQueueMapper::getInstance();
        $uploadMapper->deleteByColumnList(
            [
                'pastoral_id'            => $object->getPastoral()->getId(),
                'pastoral_attachment_id' => $object->getId(),
            ]
        );
        if (
            $this->getConfigLoader()->get(ConfigMapper::NAME_EXTERNAL_PASTORAL)
            && $this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_EXTERNAL_DELETE)
            && $object->getExternalId()
        ) {
            (new \External_Pastoral())->deletePastoralDocument($object);
            // unset external id
            $object->setExternalId(null);
            $this->save($object);
        }
    }

    /**
     * Files are considered the same if (ALL of the below are true)
     * they have the same real name
     * they have the same hash
     * they are both private / public (General / Confidential)
     *
     * @param Collection $newAttachments
     * @param Collection $oldAttachments
     * @return array
     */
    public function diffAttachments(Collection $newAttachments, Collection $oldAttachments): array
    {
        $diffMethod = static function (Model $a, Model $b) {
            return $a->getRealname() === $b->getRealname()
                && $a->getPrivate() === $b->getPrivate()
                && $a->getStorage()->getHash() === $b->getStorage()->getHash();
        };
        return [
            // created attachments
            $newAttachments->diff($oldAttachments, $diffMethod),
            // deleted attachments
            $oldAttachments->diff($newAttachments, $diffMethod),
        ];
    }
}
