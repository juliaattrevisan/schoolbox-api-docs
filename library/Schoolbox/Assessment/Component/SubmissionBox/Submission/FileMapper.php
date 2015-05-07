<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission;

use PDOStatement;
use Schoolbox\Assessment\Activity;
use Schoolbox\Assessment\ActivityCollection;
use Schoolbox\Assessment\ActivityFactory;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Storage;
use Schoolbox\UserMapper;

/**
 * Class FileMapper
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Submission
 * @method File findById($id)
 * @method File createModel()
 * @method File hydrate(array $row, $alias = '', $callback = null)
 */
class FileMapper extends Mapper
{

    /** @var Storage\Mapper */
    protected $storageMapper;


    /**
     * @return Storage\Mapper
     */
    public function getStorageMapper()
    {
        return $this->storageMapper ?: Storage\Mapper::getInstance(); // TODO remove dingleton
    }


    /**
     * @param Storage\Mapper $storageMapper
     * @return static
     */
    public function setStorageMapper($storageMapper)
    {
        $this->storageMapper = $storageMapper;
        return $this;
    }


    /**
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('student_submission');

        $file = new File();
        $file->setStorage($this->getStorageMapper()->createModel());

        $this->hasPrototype($file);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('marked', self::TYPE_BOOLEAN);
        $this->uses('late', self::TYPE_BOOLEAN);
        $this->uses('submitted', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP, 'createdAt');
        $this->uses('realname', self::TYPE_STRING, null, 'filename');
        $this->uses('uuid', self::TYPE_STRING);
        $this->uses('draft', self::TYPE_BOOLEAN);

        $this->joins('box_id', SubmissionBoxMapper::class, 'id', null, null, 'submissionBox');
        $this->joins('owner', UserMapper::class);
        $this->joins('hash', Storage\Mapper::class, 'hash', null, null, 'storage', 'storage');

        $this->registerCallback(self::CALLBACK_POSTSAVE, [ $this, 'postSave' ]);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [ $this, 'createDefaultReturn' ]);
    }


    /**
     * Post-save callback for Submission models.
     *
     * Performs the following actions:
     * * Saves a task completion for this submission's user, if there's a task
     *   related to this submission box.
     *
     * @param File $object
     * @param File $previous
     */
    protected function postSave(File $object, $previous = null)
    {
        // if there is a previous version, or this is a draft, bug out
        if ($previous || $object->isDraft()) {
            return;
        }

        // Attempt to save a task instance entry for this item.

        // fully hydrate -_-
        $submissionBox  = $this->getJoinMapper('box_id')->findById($object->getSubmissionBox()->getId());

        if ($taskInstanceId = $submissionBox->getTaskInstance()->getId()) {
            Task\InstanceCompletionMapper::getInstance()->markAsCompleted(
                $taskInstanceId, $object->getOwner()->getId()
            );
        }
    }

    /**
     * Post-save callback.
     *
     * Attaches a default return to this submission for this student, if there
     * is no return for them already.
     *
     * @param File $object
     * @param File $previous
     */
    public function createDefaultReturn(File $object, $previous = null)
    {
        if (!($object->getDraft())) {
            $boxId = $object->getSubmissionBox()->getId();
            /** @var ReturnedWorkMapper $returnMapper */
            $returnMapper = $this->getJoinMapper('box_id.return');
            $returnCount  = $returnMapper->countByColumnList([
                'box_id' => $boxId,
                'owner'  => $object->getOwner()->getId()
            ]);
            if ($returnCount === 0) {
                try {
                    // Get default state
                    $submissionStateMapper = new \DueWork_ErrorMapper();
                    $defaultState          = $submissionStateMapper->findDefaultForNewSubmissions();
                } catch (Mapper\Exception $e) {
                    // No default state: do nothing
                    return;
                }
                /** @var ReturnedWork $defaultReturn */
                $defaultReturn = $returnMapper->createModel();
                $defaultReturn
                    ->setIsConfirmationOfSubmission(true)
                    ->setSubmissionBox($object->getSubmissionBox())
                    ->setOwner($object->getOwner())
                    // No real marker
                    ->setMarker($object->getSubmissionBox()->getOwner())
                    ->setMark($defaultState->getDisplayAs())
                    ->setReturned($object->getCreatedAt())
                    ->setNormalizedMark($defaultState->getNormaliseAs())
                ;
                $returnMapper->save($defaultReturn);
            }
        }
    }

    /**
     * @return string
     */
    protected function getSelectString()
    {
        $hashJoin = $this->getJoinMapper('storage');
        $plag = \Plagscan_DocumentMapper::getInstance();

        $table = $this->getTable();

        // much join.
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('storage', 'h')},
                   {$hashJoin->getJoinColumnString('allowedFileExtension', 'm')},
                   {$this->getJoinColumnString('owner', 'u')},
                   {$plag->getColumnString('p')}
            FROM `$table`
            LEFT JOIN {$this->getJoinSql('storage', $table, 'h')}
            LEFT JOIN {$hashJoin->getJoinSql('allowedFileExtension', 'h', 'm', true)}
            LEFT JOIN {$this->getJoinSql('owner', $table, 'u')}
            LEFT JOIN {$plag->getTable()} AS `p` ON `{$table}`.`id` = `p`.`file_id`
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     * @throws Mapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = $this->createCollection();

        $plag = \Plagscan_DocumentMapper::getInstance();

        while ($r = $statement->fetch(\PDO::FETCH_ASSOC)) {

            // can't set storage if it doesn't exist
            if (!$r['h.hash']) {
                continue;
            }

            $file = $this->hydrate($r);

            $file->setPlagscan($plag->hydrate($r, 'p'));

            // just in case we wanted this to be simple

            /** @var Storage\Model $store */
            $store = $this->hydrateJoin($r, 'storage', 'h')
                         ->setMimetype(
                             $this->getJoinMapper('storage')
                                  ->hydrateJoin($r, 'allowedFileExtension', 'm')
                         );

            // ensure the path is set
            $store->setPath($this->getStorageMapper()->getManager()->calcPath($store));

            $file->setStorage($store);

            $file->setOwner($this->hydrateJoin($r, 'owner', 'u'));

            $c->add($file);
        }

        return $c;
    }


    /**
     * Gets the list of activities related to student submissions.
     *
     * An activity is returned for each submission that this user has made into
     * a submission box in the given categories.
     *
     * @param int $userId
     * @param int $limit
     * @param array $categories
     *
     * @return ActivityCollection|Activity[]
     */
    public function getActivities($userId, $limit, array $categories)
    {
        $boxJoin      = $this->getJoinMapper('box_id');
        $categoryJoin = $boxJoin->getJoinMapper('category');
        $storageJoin  = $this->getJoinMapper('storage');

        $query = "
            SELECT
                {$this->getColumnString('ss')},
                {$boxJoin->getColumnString('sb')},
                {$categoryJoin->getColumnString('c')},
                {$this->getJoinColumnString('storage', 'h')},
                {$storageJoin->getJoinColumnString('allowedFileExtension', 'afe')}
            FROM {$this->getTable()} ss
            LEFT JOIN {$this->getJoinSql('box_id', 'ss', 'sb')}
            LEFT JOIN {$this->getJoinSql('storage', 'ss', 'h')}
            LEFT JOIN {$storageJoin->getJoinSql('allowedFileExtension', 'h', 'afe', true)}
            LEFT JOIN {$boxJoin->getJoinSql('category', 'sb', 'c')}
            WHERE sb.category IN {$boxJoin->getArrayPlaceholders($categories, 'category')}
            AND ss.owner = :user_id
            AND ss.body IS NULL
            AND ss.draft IS FALSE
            ORDER BY ss.submitted DESC
            LIMIT :limit
        ";

        $params = $this->getArrayParameters($categories, 'category');
        $params['limit']   = $limit;
        $params['user_id'] = $userId;

        return $this->hydrateActivities($query, $params);
    }


    /**
     * @param int $userId
     * @param int $boxId
     * @return ActivityCollection
     */
    public function getHistory($userId, $boxId)
    {
        $boxJoin      = $this->getJoinMapper('box_id');
        $categoryJoin = $boxJoin->getJoinMapper('category');
        $storageJoin  = $this->getJoinMapper('storage');

        $query = "
            SELECT
                {$this->getColumnString('ss')},
                {$boxJoin->getColumnString('sb')},
                {$categoryJoin->getColumnString('c')},
                {$this->getJoinColumnString('storage', 'h')},
                {$storageJoin->getJoinColumnString('allowedFileExtension', 'afe')}
            FROM {$this->getTable()} ss
            LEFT JOIN {$this->getJoinSql('box_id', 'ss', 'sb')}
            LEFT JOIN {$this->getJoinSql('storage', 'ss', 'h')}
            LEFT JOIN {$storageJoin->getJoinSql('allowedFileExtension', 'h', 'afe', true)}
            LEFT JOIN {$boxJoin->getJoinSql('category', 'sb', 'c')}
            WHERE sb.id = :box_id
            AND ss.owner = :user_id
            AND ss.body IS NULL
            AND ss.draft IS FALSE
            ORDER BY ss.submitted DESC
        ";

        $params = array(
            'user_id' => $userId,
            'box_id'  => $boxId,
        );

        return $this->hydrateActivities($query, $params);
    }


    /**
     * @param string $query
     * @param array  $params
     * @return ActivityCollection
     */
    protected function hydrateActivities($query, $params)
    {
        $factory       = new ActivityFactory();
        $collection    = new ActivityCollection();

        $statement = $this->database->prepare($query);

        foreach ($params as $key => $value) {
            $statement->bindParam($key, intval($value), \PDO::PARAM_INT);
        }
        $statement->execute();
        foreach ($statement as $row) {
            $collection->add(
                $factory->buildFromStudentSubmission(
                    $this->hydrateForActivityList($row)
                )
            );
        }

        return $collection;
    }


    /**
     * Custom hydration method used by methods that get an activity list.
     *
     * This expects the following aliases:
     * * ss:  this submission
     * * sb:  submission box
     * * c:   category
     * * h:   storage
     * * afe: allowed file extension
     *
     * @param array $row
     *
     * @return File
     */
    protected function hydrateForActivityList($row) {
        $boxJoin = $this->getJoinMapper('box_id');
        $categoryJoin = $boxJoin->getJoinMapper('category');
        $storageJoin  = $this->getJoinMapper('storage');
        $mimeJoin     = $storageJoin->getJoinMapper('allowedFileExtension');

        $submission = $this->hydrate($row, 'ss');
        $box        = $boxJoin->hydrate($row, 'sb');
        $category   = $categoryJoin->hydrate($row, 'c');

        /** @var Storage\Model $storage */
        $storage    = $storageJoin->hydrate($row, 'h');
        $storage->setMimetype($mimeJoin->hydrate($row, 'afe'));

        $box->setCategory($category);
        $submission->setSubmissionBox($box);
        $submission->setStorage($storage);

        return $submission;
    }

    /**
     * Retrieves draft submissions for this user and box, ordered by most recent first.
     *
     * @param int $user_id
     * @param int $box_id
     * @param int $limit
     *
     * @return Collection|File[]
     */
    public function getDrafts($user_id, $box_id, $limit = null)
    {
        return $this->findByColumnList([
            'box_id' => $box_id,
            'owner'  => $user_id,
            'draft'  => true
        ], [
            'limit'  => $limit,
            'order'  => [ 'submitted' => 'desc' ],
        ]);

    }

    /**
     * Get list of submissions that should be submitted to crocodoc
     *
     * @return Collection|File[]
     */
    public function getCrocodoc() {

        $query = $this->getSelectString();
        $query .= "
            WHERE uuid is null AND ( ext like 'docx' OR
                                        ext like 'doc' OR
                                        ext like 'xls' OR
                                        ext like 'xlsx' OR
                                        ext like 'ppt' OR
                                        ext like 'pptx' OR
                                        ext like 'pdf')
                 AND YEAR(submitted) = YEAR(NOW())

            ";

        $statement = $this->database->prepare($query);
        $statement->execute();
        return $this->hydrateSelect($statement);
    }

} 