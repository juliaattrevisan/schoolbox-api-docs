<?php
/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Video;

use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource\FileMapper;

class ResourceVideoMapper extends Mapper
{

    protected function mapping()
    {
        $this->hasTable('video_queue');
        $this->hasModel('\\Schoolbox\\Video\\ResourceVideo');

        $this->hasMap(
            array(
                'column'     => 'video_queue_id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'hash',
                'property' => 'hash',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'type',
                'property' => 'type',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'status',
                'property' => 'status',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'duration',
                'property' => 'duration',
                'type'     => self::TYPE_INTEGER,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'file_id',
                'property' => 'fileID',
                'type'     => self::TYPE_INTEGER,
            )
        );
        $this->hasJoin(
            'file', array(
                'mapper'    => '\\Schoolbox\\Resource\\FileMapper',
                'this_key'  => 'hash',
                'other_key' => 'hash',
            )
        );
        $this->hasJoin(
            'storage', array(
                'mapper'    => '\\Schoolbox\\StorageMapper',
                'this_key'  => 'hash',
                'other_key' => 'hash',
            )
        );
        $this->hasJoin(
            'student_submission', array(
                'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\SubmissionMapper',
                'this_key'  => 'hash',
                'other_key' => 'hash',
            )
        );
        $this->hasJoin(
            'eportfolio_filelist', array(
                'mapper'    => '\\Component\\Eportfolio\\FileListMapper',
                'this_key'  => 'hash',
                'other_key' => 'hash',
            )
        );
    }

    /**
     * Find videos for a particular category
     *
     * @param  int   $category The category to search
     * @param  array $options  sort, sortdir, mime
     * @param  bool  $limit
     *
     * @return Collection
     */
    public function findByCategory($category, $options, $limit = false)
    {
        $videoList  = new Collection(array());
        $fileMapper = new FileMapper;

        $query = "
            SELECT {$this->getJoinMapper('file')->getColumnString('f')}, {$this->getColumnString('vq')}, {$fileMapper->getJoinMapper('owner')->getColumnString('u')}
            FROM `{$this->getTable()}` vq
            JOIN {$this->getJoinSQL('file', 'vq', 'f')}
            LEFT JOIN {$fileMapper->getJoinSQL('owner', 'f', 'u')}
            JOIN storage s ON s.hash = f.hash
            JOIN allowedFileExtension e ON s.ext = e.extension
            JOIN allowedFileTypes ft ON ft.fileTypeID = e.fileTypeID
            WHERE category = :cat AND vq.status != 'failed' AND f.deleted='0' AND ft.mime like :mime
        ";

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

        if ($limit != false) {
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
     * @param      $pageId
     * @param      $options
     * @param bool $limit
     *
     * @return Collection
     */
    public function findByEportfolioPage($pageId, $options, $limit = false)
    {
        $videoList      = new Collection(array());
        $myFileMapper   = new \Schoolbox\MyFileMapper;
        $fileListMapper = new \Component\Eportfolio\FileListMapper();

        $query = "
            SELECT
                {$this->getColumnString('vq')},
                {$fileListMapper->getJoinMapper('myfile')->getColumnString('mf')},
                {$myFileMapper->getJoinMapper('owner')->getColumnString('o')}
            FROM `{$this->getTable()}` vq
            LEFT JOIN {$this->getJoinSQL('eportfolio_filelist', 'vq', 'fl')}
            LEFT JOIN {$fileListMapper->getJoinSQL('myfile', 'vq', 'mf')}
            LEFT JOIN {$myFileMapper->getJoinSQL('owner', 'mf', 'o')}
            WHERE fl.page_id = :pageId
            AND vq.status != 'failed'
            AND mf.deleted = 0
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
            $query .= "LIMIT 0,:lim";
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
     * @param $id
     *
     * @return ResourceVideo
     * @throws \Schoolbox\Mapper\Exception
     */
    public function findByFileId($id)
    {
        $fileMapper = new \Schoolbox\Resource\FileMapper;

        $statement = $this->database->prepare(
            "
            SELECT {$fileMapper->getColumnString('f')}, {$this->getColumnString('vq')}
                     FROM `{$this->getTable()}` vq
                    JOIN {$this->getJoinSQL('file', 'vq', 'f')}
            WHERE f.id = ? AND vq.status = 'completed' AND vq.type='sbfs'
            "
        );

        $statement->execute(array($id));

        $video = null;
        foreach ($statement as $row) {
            $video = $this->hydrate($row, 'vq');
            $video->setFile($fileMapper->hydrate($row, 'f'));
        }
        if (!($video instanceof ResourceVideo)) {
            throw new \Schoolbox\Mapper\Exception('Instance of object not found');
        }
        return $video;

    }

    /**
     * Find a video in the MyFile table
     *
     * @param $id
     *
     * @return ResourceVideo
     * @throws \Schoolbox\Mapper\Exception
     */
    public function findByMyFileId($id)
    {
        $fileMapper = new \Schoolbox\Resource\FileMapper;

        $statement = $this->database->prepare(
            "
            SELECT
                {$fileMapper->getColumnString('mf')},
                {$this->getColumnString('vq')}
            FROM `{$this->getTable()}` vq
            JOIN {$this->getJoinSQL('file', 'vq', 'mf')}
            WHERE mf.id = ?
            AND vq.status = 'completed'
            AND vq.type='myfile'
        "
        );

        $statement->execute(array($id));

        $video = null;

        foreach ($statement as $row) {
            $video = $this->hydrate($row, 'vq');
            $video->setFile($fileMapper->hydrate($row, 'mf'));
        }

        if (!($video instanceof ResourceVideo)) {
            throw new \Schoolbox\Mapper\Exception('Instance of object not found');
        }

        return $video;
    }

    public function findByStorageId($id)
    {
        $storageMapper = new \Schoolbox\StorageMapper;

        $statement = $this->database->prepare(
            "
            SELECT {$storageMapper->getColumnString('s')}, {$this->getColumnString('vq')}
                     FROM `{$this->getTable()}` vq
                    JOIN {$this->getJoinSQL('storage', 'vq', 's')}
            WHERE s.storage_id = ? AND vq.status = 'completed' AND vq.type='storage'
            "
        );

        $statement->execute(array($id));

        $video = null;
        foreach ($statement as $row) {
            $video = $this->hydrate($row, 'vq');
            $video->setFile($storageMapper->hydrate($row, 's'));
        }

        if (!($video instanceof ResourceVideo)) {
            throw new \Schoolbox\Mapper\Exception('Instance of object not found');
        }
        return $video;

    }

    /**
     * Like findByStorageId() except it accepts a brownhole hash instead of a
     * brownhole storage ID.
     *
     * @throws \Schoolbox\Mapper\Exception if no video with this hash was found
     *
     * @param  string $hash
     *
     * @return ResourceVideo
     */
    public function findByStorageHash($hash)
    {

        $storageMapper = $this->getJoinMapper('storage');
        $fileMapper = $this->getJoinMapper('file');

        $statement     = $this->database->prepare(
            "
            SELECT {$storageMapper->getColumnString('s')}, {$this->getColumnString('vq')},  {$fileMapper->getColumnString('f')}
            FROM `{$this->getTable()}` vq
            JOIN {$this->getJoinSQL('storage', 'vq', 's')}
            JOIN {$this->getJoinSQL('file', 'vq', 'f')}
            WHERE s.hash = ? AND vq.status = 'completed'
        "
        );

        $statement->execute(array($hash));

        $video = null;
        if (($row = $statement->fetch())) {
            $video = $this->hydrate($row, 'vq');
            $video->setStorage($storageMapper->hydrate($row, 's'));
            $video->setFile($fileMapper->hydrate($row, 'f'));
        }

        if (!($video instanceof ResourceVideo)) {
            throw new \Schoolbox\Mapper\Exception('Instance of object not found');
        }
        return $video;
    }

    /**
     * Gets a video for a submission, regardless of the video's processing status
     *
     * @param $id
     *
     * @return ResourceVideo
     * @throws \Schoolbox\Mapper\Exception
     */
    public function findBySubmissionId($id)
    {
        $studentSubmissionMapper = $this->getJoinMapper('student_submission');
        $statement               = $this->database->prepare(
            "
            SELECT {$studentSubmissionMapper->getColumnString('s')}, {$this->getColumnString('vq')}
            FROM `{$this->getTable()}` vq
            INNER JOIN {$this->getJoinSQL('student_submission', 'vq', 's')}
            WHERE s.id = ? AND vq.type='submission'
        "
        );

        $statement->execute(array($id));

        $video = null;
        if (($row = $statement->fetch())) {
            $video = $this->hydrate($row, 'vq');
            $video->setFile($studentSubmissionMapper->hydrate($row, 's'));
        }

        if (!($video instanceof ResourceVideo)) {
            throw new \Schoolbox\Mapper\Exception('Instance of object not found');
        }

        return $video;
    }
}
