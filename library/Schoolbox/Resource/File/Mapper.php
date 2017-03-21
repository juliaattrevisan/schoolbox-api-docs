<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\File;

use Alaress_Byte_Format;
use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\IHomepageCleanable;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Resource;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage\Manager;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\User;
use Schoolbox\Video\Queue;
use Schoolbox\Video\QueueMapper;

/**
 * Class Mapper
 *
 * File Mapper. Maps file table to SBFS file objects.
 *
 * @package Schoolbox\Resource\File
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model findById($id)
 */
class Mapper extends Resource\AbstractFileMapper implements ILocator, IFileMapper, Searchable, IHomepageCleanable
{
    /** @var Queue $queue */
    protected $queue = null;

    /**
     * @return string
     */
    protected function getFolderFieldname()
    {
        return 'category';
    }


    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        parent::mapping();

        $this->hasTable('file');
        $this->hasModel(Model::class);

        $this->uses('comment', self::TYPE_STRING, null, 'description'); // TODO rename that field!
        $this->uses('hidden', self::TYPE_BOOLEAN);
        $this->uses('modified', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP, 'modifiedAt');

        $this->joins('category', Resource\Folder\Mapper::class, 'id', null, null, 'folder', 'category');

//        // defined longwise because $this->joins doesn't support extra
//        $this->hasJoin(
//            'queue',
//            [
//                'mapper'    => ResourceVideoMapper::class,
//                'this_key'  => 'id',
//                'other_key' => 'file_id',
//                'extra'     => ":other_alias.type = 'sbfs'",
//            ]
//        );

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'ensureOwnerIsNotNull']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, self::CALLBACK_PRESAVE]);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, self::CALLBACK_POSTSAVE]);
    }

    /**
     * Port of handler method `File->getAllInCategory()`.
     *
     * @param array  $folderIds
     * @param array  $options
     *               * name: an exact filename to show
     *               * size: number of items to show
     *               * filter: list of filetypes to show (all, audio, video, image, document)
     *               * sort: field to sort on (name, date, random)
     *               * sortdir: direction to sort on (asc, desc)
     *               * getHidden: get hidden files too (default to false)
     *
     * @return Collection|Model[]
     */
    public function findForFolders($folderIds, $options = array())
    {
        $name          = isset($options['name']) ? $options['name'] : '';
        $limit         = !empty($options['size']) ? intval($options['size']) : 0;
        $getHidden     = !empty($options['getHidden']);
        $fileTypes     = !empty($options['filter']) ? $options['filter'] : array();
        $sortColumn    = !empty($options['sort']) ? $options['sort'] : 'name';
        $sortDirection = !empty($options['sortdir']) ? $options['sortdir'] : 'asc';
        if (empty($folderIds)) {
            return new Collection();
        }
        // much join many wow
        $table     = $this->getTable();
        $baseQuery = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('hash', 'h')},
                {$this->getJoinColumnString('hash.allowedFileExtension', 'm')},
                {$this->getJoinColumnString('owner', 'u')},
                {$this->getJoinColumnString($this->getFolderFieldname(), 'c')}
            FROM {$table}
            LEFT JOIN {$this->getJoinSql('hash', $table, 'h')}
            LEFT JOIN {$this->getJoinSql('hash.allowedFileExtension', 'h', 'm', true)}
            LEFT JOIN {$this->getJoinSql('owner', $table, 'u')}
            LEFT JOIN {$this->getJoinSql($this->getFolderFieldname(), $table, 'c')}
        ";

        // Slam together a queery
        $whereParts  = [
            "category IN {$this->getArrayPlaceholders($folderIds, 'folder')}"
        ];
        $whereParams = $this->getArrayParameters($folderIds, 'folder');
        if (!$getHidden) {
            $whereParts[] = "$table.hidden IS FALSE";
        }

        if (strlen($name)) {
            $whereParts[]            = "$table.description = :filename";
            $whereParams['filename'] = $name;
        }

        // Put together mimetype filters
        $fileTypeMimes = [
            'document' => [
                "%pdf%",
                "%msword%",
                "%vnd.ms%",
                "%rtf%",
                "%text%",
                "%document%",
                "%powerpoint%",
            ],
            'image' => ["%image%",],
            'audio' => ["%audio%",],
            'video' => ["%video%", "%flash%", "%realmedia%",],
        ];

        if (!is_array($fileTypes)) {
            $fileTypes = [$fileTypes];
        }
        if (!in_array('all', $fileTypes)) {
            $mimeParts      = [];
            $otherMimeParts = [];
            foreach ($fileTypes as $fileType) {
                if (!empty($fileTypeMimes[$fileType])) {
                    foreach ($fileTypeMimes[$fileType] as $mime) {
                        $mimeParts[] = "m.mime LIKE '$mime'";
                    }
                } elseif ($fileType == 'other') {
                    // Other: any not in document, image, audio or video
                    foreach ($fileTypeMimes as $fileType => $mimesForFileType) {
                        foreach ($mimesForFileType as $mime) {
                            $otherMimeParts[] = "m.mime LIKE '$mime'";
                        }
                    }
                    //Return all files regardless their extension
                    $mimeParts[] = "h.ext = '' OR m.fileTypeId IS NULL";
                }
            }
            if (!empty($otherMimeParts)) {
                $mimeParts[] = 'NOT('.implode(' OR ', $otherMimeParts).')';
            }
            if (!empty($mimeParts)) {
                $whereParts[] = implode(' OR ', $mimeParts);
            }
        }
        $baseQuery .= ' WHERE '.$table.'.deleted_at IS NULL AND ('.implode(') AND (', $whereParts).')';

        // Put together sort parts
        $sortFields = array(
            'name'   => "$table.description",
            'date'   => "$table.submitted",
            'random' => "RAND()",
        );
        $sortColumn    = isset($sortFields[$sortColumn]) ? $sortColumn : 'name';
        $sortDirection = strtoupper($sortDirection) == 'DESC' ? 'DESC' : 'ASC';
        $baseQuery    .= " ORDER BY " . $sortFields[$sortColumn] . " " . $sortDirection;

        if ($limit > 0) {
            $baseQuery .= " LIMIT 0, :limit";
        }

        $statement = $this->getDatabase()->prepare($baseQuery);
        foreach ($whereParams as $param => $value) {
            $statement->bindValue($param, $value);
        }
        if ($limit > 0) {
            $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        }
        $statement->execute();
        return $this->hydrateSelect($statement);
    }


    /**
     * Performs a search with the given parameters, and returns a Collection of the resultant set.
     *
     * @param User   $user
     * @param string $keyword
     * @param int    $offset
     * @param int    $limit
     * @param string $sortProperty
     * @param string $sortDirection
     * @return Resource\File\Search\Result
     */
    public function search($user, $keyword, $offset = 0, $limit = self::DEFAULT_LIMIT, $sortProperty = '', $sortDirection = 'DESC')
    {
        if ($limit == 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        $searcher = new Resource\File\Search\Result();
        $searcher->search($user, $this, $keyword, $sortProperty, $sortDirection, $offset, $limit);

        return $searcher;
    }

    /**
     * Pre-save callback: ensures that owner is not null.
     *
     * @param  Model $model
     * @return bool  Always true
     */
    public function ensureOwnerIsNotNull(Model $model)
    {
        if ($model->getOwner() instanceof User) {
            if (!$model->getOwner()->getId()) {
                $dummyUser = new User();
                $dummyUser->setId(0);
                $model->setOwner($dummyUser);
            }
        }
        return true;
    }

    /**
     * Pre-save callback: Makes sure the same file does not already exist in the folder, and also handles adding to video queue
     * if it is required
     *
     * @param Model $model
     * @return bool NOT always true (Now prevents duplicate files being added to the same folder)
     */
    public function preSave(Model $model)
    {
        $queueMapper = QueueMapper::getInstance();

        // Check if the file uploaded was a video/audio file requiring transcoding

        if ($model->getMimetype()->isVideo() || $model->getMimetype()->isAudio()) {

            // Houston- we need a transcode

            try {

                // Check if there is an existing transcode request in Complete/Pending/Processing state. If there is, then we
                // don't want to create a duplicate transcode request

                $queueMapper->findOneByColumnList([
                    'hash' => $model->getHash(),
                    'status' => [
                        QueueMapper::STATUS_COMPLETED,
                        QueueMapper::STATUS_PENDING,
                        QueueMapper::STATUS_PROCESSING
                    ]
                ]);

                // There is an existing transcode request for this file that hasn't completed/failed, we don't want to
                // touch the video_queue table so we leave $this->queue set to null

            } catch (MapperException $e) {

                // There was no existing/outstanding transcode request, so we need to kick one off. Start creating a record
                // in $this->queue. We might not be able to complete this yet however. To create the record we require a file ID
                // which we will only have in pre-save if there was an existing file record... which we will find out in a second
                // when we check for duplicate uploads.

                /** @var Queue $queue */
                $this->queue = $queueMapper->createModel();
                $this->queue->setStorage($model->getStorage());
                $this->queue->setStatus(QueueMapper::STATUS_PENDING);
                $this->queue->setCreatedAt(Alaress_Date_Format::now());
                $this->queue->setUpdatedAt(Alaress_Date_Format::now());
            }
        }

        // Check if the same file had already been uploaded to the folder so we don't create duplicate files in the same folder

        try {

            /** @var Model $existingFile */
            $existingFile = $this->findOneByColumnList([
                'category' => $model->getFolder()->getId(),
                'hash' => $model->getHash()
            ]);

            // Did we need to create a transcode request?

            if ($this->queue != null) {

                // We need a transcode record to be created...

                // The same file already existed in the file table, we can use it on our transcode request and finish off
                // the creation of the transcode record now

                $this->queue->setFile($existingFile);
                $queueMapper->save($this->queue);
                $this->queue = null;
            }

            // The file already existed, return false because we don't want to add it again
            return false;

        } catch (MapperException $e) {

            // There is no duplicate file in the file table, so we can go ahead and save this file record.

            // We can't however finish the saving of the transcode request until the file model has been saved, so we will
            // leave this hanging for now, and finish it off in the postSave() function below...

            return true;
        }
    }

    /**
     * @param Model $file
     * @param Model $previous
     * @return bool
     */
    protected function postSave(Model $file, Model $previous = null)
    {
        // Check if we left a transcode request hanging...

        if ($this->queue != null) {

            // We did! Now that we have the file record saved we can use it to finish off the video_queue record.

            $queueMapper = QueueMapper::getInstance();

            $this->queue->setFile($file);
            $queueMapper->save($this->queue);
        }

        return true;
    }

    /**
     * Soft-deletes content for specified homepage
     *
     * @param int $cid
     * @return void
     */
    public function homepageClean($cid){
        $this->deleteByColumn('category',$cid);
    }
}
