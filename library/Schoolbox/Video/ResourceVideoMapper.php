<?php

/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Video;

use Component\Eportfolio\FileListMapper;
use Component_Config_Size;
use Schoolbox;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper;
use Schoolbox\Collection;
use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\My\File\Mapper as MyFileMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

/**
 * Class ResourceVideoMapper
 *
 * @package Schoolbox\Video
 */
class ResourceVideoMapper extends Mapper
{

    /**
     * @return void
     * @throws Mapper\DefinitionException
     * @throws \InvalidArgumentException
     */
    protected function mapping()
    {
        $this->hasTable('video_queue');
        $this->hasModel(ResourceVideo::class);
        $this->uses('video_queue_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->uses('status', self::TYPE_STRING);
        $this->uses('duration', self::TYPE_INTEGER);
        $this->joins('hash', FileMapper::class, 'hash', null, null, 'file', 'file');
        $this->joins('hash', StorageMapper::class, 'hash', null, null, 'storage', 'storage');

        $this->hasJoin('myfile', [
            'mapper'    => MyFileMapper::class,
            'this_key'  => 'hash',
            'other_key' => 'hash',
        ]);

        $this->hasJoin('student_submission', [
            'mapper'    => SubmissionMapper::class,
            'this_key'  => 'hash',
            'other_key' => 'hash',
        ]);

        $this->hasJoin('eportfolio_filelist', [
            'mapper'    => FileListMapper::class,
            'this_key'  => 'id',
            'other_key' => 'file_id',
        ]);
    }


    /**
     * Find videos for a particular category
     *
     * @param int      $category The category to search
     * @param array    $options  sort, sortdir, mime, getHidden
     * @param int|null $limit
     * @return Collection|ResourceVideo[]
     * @throws MapperException
     * @throws Mapper\DefinitionException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function findByCategory(int $category, array $options, ?int $limit = Component_Config_Size::SIZE_ALL): Collection
    {
        $videoList  = $this->createCollection();
        $fileMapper = FileMapper::getInstance();

        $query = "
            SELECT    {$this->getJoinMapper('file')->getColumnString('f')},
                      {$this->getColumnString('vq')},
                      {$fileMapper->getJoinMapper('owner')->getColumnString('u')}
            FROM      `{$this->getTable()}` vq
            JOIN      {$this->getJoinSQL('file', 'vq', 'f')}
            LEFT JOIN {$fileMapper->getJoinSQL('owner', 'f', 'u', true)}
            JOIN      storage s ON s.hash = f.hash
            JOIN      allowedFileExtension e ON s.ext = e.extension
            JOIN      allowedFileTypes ft ON ft.fileTypeID = e.fileTypeID
            WHERE     category = :cat AND
                      vq.status != 'failed' AND
                      ft.mime like :mime
        ";

        /* hide hidden */
        if (isset($options['getHidden']) && !$options['getHidden']) {
            $query .= " AND f.hidden = false ";
        }

        /* Sort order
         */
        $sortFields = array(
            'name' => 'f.description',
            'date' => 'f.submitted'
        );

        /* Really?
         * http://stackoverflow.com/questions/2542410/how-do-i-set-order-by-params-using-prepared-pdo-statement
         */
        if (in_array($options['sort'], array_keys($sortFields))) {
            if (in_array(strtoupper($options['sortdir']), array('DESC', 'ASC'))) {
                $query .= "
                    ORDER BY {$sortFields[$options['sort']]} {$options['sortdir']}
                ";
            }
        }

        if ($limit) {
            $query .= "LIMIT 0,:lim";
        }

        // Logically, mimetype should always be video/*
        if (!isset($options['mime'])) {
            $options['mime'] = 'video';
        }
        $mime      = $options['mime'] . '/%';
        $statement = $this->database->prepare($query);
        $statement->bindParam(':cat', $category);
        $statement->bindParam(':mime', $mime);

        if ($limit != false) {
            $statement->bindParam(':lim', intval($limit), \PDO::PARAM_INT);
        }

        $statement->execute();

        foreach ($statement as $row) {
            $video = $this->hydrate($row, 'vq');
            $video->setFile($fileMapper->hydrate($row, 'f'));

            if ($row['u.id']) {
                $video->getFile()->setOwner($fileMapper->getJoinMapper('owner')->hydrate($row, 'u'));
            } else {
                $video->getFile()->setOwner($fileMapper->getJoinMapper('owner')->getUnknownUser());
            }
            $videoList->add($video);
        }

        return $videoList;
    }


    /**
     * Find videos for a particular MyFiles user
     *
     * @param int $pageId
     * @param array $options
     * @param bool $limit
     * @return Collection
     * @throws MapperException
     * @throws Mapper\DefinitionException
     * @throws \PDOException
     */
    public function findByEportfolioPage($pageId, $options, $limit = false)
    {
        $videoList      = new Collection();
        $myFileMapper   = MyFileMapper::getInstance();
        $fileListMapper = FileListMapper::getInstance();

        $query = "
            SELECT
                {$this->getColumnString('vq')},
                {$fileListMapper->getJoinMapper('myfile')->getColumnString('mf')},
                {$myFileMapper->getJoinMapper('owner')->getColumnString('o')}
            FROM `{$this->getTable()}` vq
            JOIN `myFile` AS `mf` ON `vq`.`hash` = `mf`.`hash` AND `mf`.`deleted_at` IS NULL
            JOIN {$this->getJoinSQL('eportfolio_filelist', 'mf', 'fl')}
            LEFT JOIN {$myFileMapper->getJoinSQL('owner', 'mf', 'o')}
            WHERE fl.page_id = :pageId
            AND vq.status != 'failed'
        ";
        /* Sort order
         */
        $sortFields = array(
            'name' => 'mf.description',
            'date' => 'mf.submitted'
        );

        /* Really?
         * http://stackoverflow.com/questions/2542410/how-do-i-set-order-by-params-using-prepared-pdo-statement
         */
        if (in_array($options['sort'], array_keys($sortFields))) {
            if (in_array(strtoupper($options['sortdir']), array('DESC', 'ASC'))) {
                $query .= "
                    ORDER BY {$sortFields[$options['sort']]} {$options['sortdir']}
                ";
            }
        }

        if ($limit != false) {
            $query .= 'LIMIT 0,:lim';
        }

        $statement = $this->database->prepare($query);
        $statement->bindParam(':pageId', $pageId);

        if ($limit != false) {
            $statement->bindParam(':lim', $limit, \PDO::PARAM_INT);
        }

        $statement->execute();

        foreach ($statement as $row) {
            $video = $this->hydrate($row, 'vq');
            $video->setFile($myFileMapper->hydrate($row, 'mf'));

            if ($row['o.id']) {
                $video->getFile()->setOwner($myFileMapper->getJoinMapper('owner')->hydrate($row, 'o'));
            } else {
                $video->getFile()->setOwner($myFileMapper->getJoinMapper('owner')->getUnknownUser());
            }

            $videoList->add($video);
        }

        return $videoList;
    }


    /**
     * @param int $id
     * @return ResourceVideo
     * @throws MapperException
     * @throws MapperException\NotFound
     * @throws Mapper\DefinitionException
     * @throws \PDOException
     */
    public function findByFileId($id)
    {
        $fileMapper = FileMapper::getInstance();

        $statement = $this->database->prepare(
            "
            SELECT {$fileMapper->getColumnString('f')}, {$this->getColumnString('vq')}
                     FROM `{$this->getTable()}` vq
                    JOIN {$this->getJoinSQL('file', 'vq', 'f')}
            WHERE f.id = ? AND vq.status != 'failed'
            "
        );

        $statement->execute(array($id));

        $video = null;
        foreach ($statement as $row) {
            $video = $this->hydrate($row, 'vq');
            $video->setFile($fileMapper->hydrate($row, 'f'));
        }
        if (!($video instanceof ResourceVideo)) {
            throw new MapperException\NotFound();
        }
        return $video;
    }


    /**
     * Find a video in the MyFile table
     *
     * @param int $id
     *
     * @return ResourceVideo
     * @throws MapperException
     * @throws MapperException\NotFound
     * @throws Mapper\DefinitionException
     * @throws \PDOException
     * @deprecated
     */
    public function findByMyFileId($id)
    {
        $fileMapper = MyFileMapper::getInstance();

        $statement = $this->database->prepare(
            "
            SELECT
                {$fileMapper->getColumnString('mf')},
                {$this->getColumnString('vq')}
            FROM `{$this->getTable()}` vq
            JOIN {$this->getJoinSQL('myfile', 'vq', 'mf')}
            WHERE mf.id = ?
            AND vq.status != 'failed'
        "
        );

        $statement->execute(array($id));

        $video = null;

        foreach ($statement as $row) {
            $video = $this->hydrate($row, 'vq');
            $video->setFile($fileMapper->hydrate($row, 'mf'));
        }

        if (!($video instanceof ResourceVideo)) {
            throw new MapperException\NotFound();
        }

        return $video;
    }


    /**
     * Like findByStorageId() except it accepts a brownhole hash instead of a
     * brownhole storage ID.
     *
     * @param string $hash
     *
     * @return ResourceVideo
     * @throws MapperException if no video with this hash was found
     * @throws MapperException\NotFound
     * @throws Mapper\DefinitionException
     * @throws \PDOException
     */
    public function findByStorageHash($hash)
    {
        $storageMapper = $this->getJoinMapper('storage');

        $sql = "
            SELECT      {$storageMapper->getColumnString('s')},
                        {$this->getColumnString('vq')},
                        {$storageMapper->getJoinColumnString('allowedFileExtension', 'mime')}
            FROM        `{$this->getTable()}` vq
            JOIN        {$this->getJoinSQL('storage', 'vq', 's')}
            LEFT JOIN   {$storageMapper->getJoinSql('allowedFileExtension', 's', 'mime', true)}
            WHERE       s.hash = ? AND
                        vq.status != 'failed'
        ";

        $statement = $this->database->prepare($sql);
        $statement->execute([$hash]);

        /** @var MimetypeMapper $mimeMapper */
        $mimeMapper = $storageMapper->getJoinMapper('allowedFileExtension');

        $video = null;

        if (($row = $statement->fetch())) {
            $video = $this->hydrate($row, 'vq');

            $storageObject = $storageMapper->hydrate($row, 's');

            /** @var Mimetype $mime */
            $mime = $mimeMapper->hydrate($row, 'mime');

            if ($mime instanceof MimeType) {
                $storageObject->setMimetype($mime);
            }

            $video->setStorage($storageObject);
        }

        if (!($video instanceof ResourceVideo)) {
            throw new MapperException\NotFound();
        }
        return $video;
    }
}
