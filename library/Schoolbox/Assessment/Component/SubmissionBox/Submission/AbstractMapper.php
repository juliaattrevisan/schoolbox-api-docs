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
use Schoolbox\Assessment\ActivityCollection;
use Schoolbox\Assessment\ActivityFactory;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\IActivityMapper;
use Schoolbox\Mapper;
use Schoolbox\Resource\Storage;
use Schoolbox\UserMapper;

abstract class AbstractMapper extends Mapper implements IActivityMapper
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
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('student_submission');

        // note the lack of prototype/model declaration

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('marked', self::TYPE_BOOLEAN);
        $this->uses('late', self::TYPE_BOOLEAN);
        $this->uses('submitted', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP, 'createdAt');

        $this->joins('box_id', SubmissionBoxMapper::class, 'id', null, null, 'submissionBox');
        $this->joins('owner', UserMapper::class);

        $this->registerCallback(self::CALLBACK_POSTSAVE, [ $this, 'postSave' ]);
    }


    /**
     * Post-save callback for Submission models.
     *
     * Performs the following actions:
     * * Saves a task completion for this submission's user, if there's a task
     *   related to this submission box.
     *
     * @param ISubmission $object
     * @param ISubmission $previous
     */
    protected function postSave(ISubmission $object, $previous = null)
    {
        // if there is a previous version, or this is a draft, bug out
        if ($previous || ($object instanceof Inline && $object->isDraft())) {
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
     * @param PDOStatement $statement
     * @throws Mapper\Exception
     * @return \Schoolbox\Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {

        $class  = $this->getModel();

        // confirm that we retrieved the correct type of Submission
        $collection = $collection->filter(function ($item) use ($class) {
            return $item instanceof $class;
        });

        return $collection;
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
     * * ss: this submission
     * * sb: submission box
     * * c:  category
     *
     * @param array $row
     *
     * @return ISubmission
     */
    protected abstract function hydrateForActivityList($row);

} 