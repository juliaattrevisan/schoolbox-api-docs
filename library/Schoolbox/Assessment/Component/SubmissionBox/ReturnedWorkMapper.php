<?php
/**
 * Mapper for the Submission model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;

use Schoolbox;
use Schoolbox\Assessment\ActivityFactory;
use Schoolbox\Assessment\ActivityCollection;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Task;

/**
 * Class ReturnedWorkMapper
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox
 * @method ReturnedWork findById($id)
 * @method ReturnedWork hydrate(array $row, $alias = '', $callback = null)
 * @method ReturnedWork createModel()
 */
class ReturnedWorkMapper extends Schoolbox\Mapper implements Schoolbox\Assessment\IActivityMapper
{

    protected function mapping()
    {
        $this->hasTable('submission_return');
        $this->hasModel('\\Schoolbox\\Assessment\\Component\\SubmissionBox\\ReturnedWork');
        $this->hasMap(
            array(
                'column' => 'id',
                'property' => 'id',
                'type' => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );
        $this->hasMap(
            array(
                'column' => 'box_id',
                'property' => 'submissionBox',
                'type' => self::TYPE_MODEL,
                'join' => 'submission_box',
            )
        );
        $this->hasMap(
            array(
                'column' => 'owner',
                'property' => 'owner',
                'type' => self::TYPE_MODEL,
                'join' => 'owner',
            )
        );
        $this->hasMap(
            array(
                'column' => 'marker',
                'property' => 'marker',
                'type' => self::TYPE_MODEL,
                'join' => 'marker',
            )
        );
        $this->hasMap(
            array(
                'column' => 'comment',
                'property' => 'comment',
                'type' => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column' => 'mark',
                'property' => 'mark',
                'type' => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column' => 'norm_mark',
                'property' => 'normalizedMark',
                'type' => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column' => 'submitted',
                'property' => 'submitted',
                'type' => self::TYPE_DATETIME,
            )
        );
        $this->hasMap(
            array(
                'column' => 'returned',
                'property' => 'returned',
                'type' => self::TYPE_DATETIME,
                'updated_timestamp' => true,
            )
        );
        $this->hasMap(
            array(
                'column' => 'uuid',
                'property' => 'uuid',
                'type' => self::TYPE_STRING,
            )
        );

        $this->uses('is_confirmation_of_submission', self::TYPE_BOOLEAN);

        $this->hasSoftDeleteBooleanField('deleted');

        $this->hasJoin(
            'submission_box',
            array(
                'mapper' => '\\Schoolbox\\Assessment\\Component\\SubmissionBoxMapper',
                'this_key' => 'box_id',
                'other_key' => 'id',
            )
        );
        $this->hasJoin(
            'owner',
            array(
                'mapper' => '\\Schoolbox\\UserMapper',
                'this_key' => 'owner',
                'other_key' => 'id',
            )
        );
        $this->hasJoin(
            'marker',
            array(
                'mapper' => '\\Schoolbox\\UserMapper',
                'this_key' => 'marker',
                'other_key' => 'id',
            )
        );

        $this->joins('hash', Schoolbox\Resource\Storage\Mapper::class, 'hash', null, null, 'storage', 'storage');

        $this->registerCallback('postSave', array($this, 'postSave'));
    }

    /**
     * Gets the list of activities related to submission returns.
     *
     * An activity is returned for each submission return for this user in the
     * given categories.
     *
     * @param int $user_id The user for who activities are fetched for.
     * @param int $limit The max number of activities to be fetched.
     * @param array $categories The categories to fetch activities for.
     * @param array $visibilities Which MARK_VISIBILITY* constants are viewable?
     *
     * @return ActivityCollection
     */
    public function getActivities($user_id, $limit, array $categories, array $visibilities = [ SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT ])
    {

        //
        // User mapper: I want to know if the user (requesting user, not user
        // whose activities are fetched) is a parent, or a student.
        // Parent:  sb.visibility = ALL
        // Student: sb.visibility IN (ALL, STUDENTS)

        $collection = new ActivityCollection();
        $factory = new ActivityFactory();
        $box_join = $this->getJoinMapper('submission_box');
        $category_join = $box_join->getJoinMapper('category');
        $storage_join = $this->getJoinMapper('storage');
        $mime_join = $storage_join->getJoinMapper('allowedFileExtension');

        $query = "
			SELECT
				{$this->getColumnString('sr')},
				{$box_join->getColumnString('sb')},
				{$category_join->getColumnString('c')},
				{$storage_join->getColumnString('file')},
				{$mime_join->getColumnString('mime')}
			FROM {$this->getTable()} sr
			LEFT JOIN {$this->getJoinSql('submission_box', 'sr', 'sb')}
			LEFT JOIN {$box_join->getJoinSql('category', 'sb', 'c')}
			LEFT JOIN {$this->getJoinSql('storage', 'sr', 'file')}
			LEFT JOIN {$storage_join->getJoinSql('allowedFileExtension', 'file', 'mime', true)}
			WHERE sb.category IN {$this->getArrayPlaceholders($categories, 'category')}
			AND sb.mark_visibility IN {$this->getArrayPlaceholders($visibilities, 'vis')}
			AND sr.owner = :user_id
			AND sr.deleted IS FALSE
			AND sr.is_confirmation_of_submission IS FALSE
			ORDER BY sr.returned DESC
			LIMIT :limit
		";
        $statement = $this->database->prepare($query);
        $params = $this->getArrayParameters($categories, 'category') + $this->getArrayParameters($visibilities, 'vis');
        $params['limit'] = $limit;
        $params['user_id'] = $user_id;
        foreach ($params as $key => $value) {
            $statement->bindParam($key, intval($value), \PDO::PARAM_INT);
        }
        $statement->execute();
        foreach ($statement as $row) {
            $collection->add(
                $factory->buildFromReturnedWork(
                    $this->hydrateForActivityList($row)
                )
            );
        }
        return $collection;
    }

    /**
     * Builds list of student's marks for all returns in the given category.
     *
     * @param int $category_id
     * @param int $student_id
     * @param array $extra_params Any extra filters:
     *                            * year
     *                            * semester
     *                            * owner - the creator of the submission box
     *                            * visibility - a list containing the allowed
     *                              submission box mark visibility values.
     *
     * @return array
     */
    public function getStudentMarks($category_id, $student_id, $extra_params = array())
    {
        /** @var SubmissionBoxMapper $submission_box_mapper */
        $submission_box_mapper = $this->getJoinMapper('submission_box');

        //
        // Build filters for query
        $params = array(
            'category' => $category_id,
            'student' => $student_id,
        );
        $filters = array(
            'sb.category = :category',
            'sr.owner = :student',
            'sr.deleted IS FALSE',
            'sb.deleted IS FALSE',
            'sb.task_id IS NULL',
        );
        if (!empty($extra_params['year'])) {
            $params['year'] = $extra_params['year'];
            $filters[] = 'YEAR(sb.date) = :year';
        }
        if (!empty($extra_params['semester'])) {
            $semester_params = $submission_box_mapper->getSemesterMonths($extra_params['semester']);
            $params['month_start'] = $semester_params['start'];
            $params['month_end'] = $semester_params['end'];
            $filters[] = 'MONTH(sb.date) BETWEEN :month_start AND :month_end';
        }
        if (!empty($extra_params['owner'])) {
            $params['owner'] = $extra_params['owner'];
            $filters[] = 'sb.owner = :owner';
        }
        if (!empty($extra_params['visibility'])) {
            $params = array_merge($params, $this->getArrayParameters($extra_params['visibility'], 'vis'));
            $filters[] = 'sb.mark_visibility IN ' . $this->getArrayPlaceholders($extra_params['visibility'], 'vis');
        }
        $filters = implode(' AND ', $filters);

        // Get submission grade group, but only for letter grade submissions
        $query = "
			SELECT
				sb.id AS box_id,
				sr.mark,
				sr.norm_mark,
				sr.returned,
				sb.weight,
				sb.mark_type,
				sb.submission_grade_group_id
			FROM {$this->getTable()} sr
			LEFT JOIN {$this->getJoinSql('submission_box', 'sr', 'sb')}
			WHERE $filters
			ORDER BY sr.returned DESC
		";
        $statement = $this->database->prepare($query);
        $statement->execute($params);

        //
        // Ignore all but the most recent mark for each submission box.
        $marks = array();
        foreach ($statement as $row) {
            $box_id = $row['box_id'];
            $mark = $row['norm_mark'];
            $pretty_mark = $row['mark'];
            if (!array_key_exists($box_id, $marks)) {
                $marks[$box_id] = array(
                    'mark'                      => $mark,
                    'pretty_mark'               => $pretty_mark,
                    'date'                      => $row['returned'],
                    'weight'                    => $row['weight'],
                    'mark_type'                 => $row['mark_type'],
                    'submission_grade_group_id' => $row['submission_grade_group_id'],
                );
            }
        }
        return $marks;
    }

    /**
     * Retrieves activity list for the student's submission history.
     *
     * @param $user_id
     * @param $box_id
     *
     * @return ActivityCollection
     */
    public function getHistory($user_id, $box_id)
    {
        $collection = new ActivityCollection();
        $factory = new ActivityFactory();
        $box_join = $this->getJoinMapper('submission_box');
        $category_join = $box_join->getJoinMapper('category');
        //
        // Have hydrateFromRow() in ActivityFactory
        $query = "
			SELECT
				{$this->getColumnString('sr')},
				{$box_join->getColumnString('sb')},
				{$category_join->getColumnString('c')},
				{$this->getJoinColumnString('storage', 'file')},
				{$this->getJoinColumnString('storage.allowedFileExtension', 'mime')}
			FROM {$this->getTable()} sr
			LEFT JOIN {$this->getJoinSql('submission_box', 'sr', 'sb')}
			LEFT JOIN {$box_join->getJoinSql('category', 'sb', 'c')}
			LEFT JOIN {$this->getJoinSql('storage', 'sr', 'file')}
			LEFT JOIN {$this->getJoinSql('storage.allowedFileExtension', 'file', 'mime')}
			WHERE sr.owner = :user_id
			AND sb.id = :box_id
			AND sr.deleted IS FALSE
			AND sr.is_confirmation_of_submission IS FALSE
			ORDER BY sr.returned DESC
		";
        $statement = $this->database->prepare($query);
        $params['user_id'] = $user_id;
        $params['box_id'] = $box_id;
        foreach ($params as $key => $value) {
            $statement->bindParam($key, intval($value), \PDO::PARAM_INT);
        }
        $statement->execute();
        foreach ($statement as $row) {
            $collection->add(
                $factory->buildFromReturnedWork(
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
     * * sr:   this return
     * * sb:   submission box
     * * c:    category
     * * file: attached storage, if applicable
     *
     * @param array $row
     *
     * @return ReturnedWork
     */
    protected function hydrateForActivityList($row)
    {
        $box_join      = $this->getJoinMapper('submission_box');
        $category_join = $box_join->getJoinMapper('category');
        $storage_join  = $this->getJoinMapper('storage');
        $mime_join     = $storage_join->getJoinMapper('allowedFileExtension');

        $return   = $this->hydrate($row, 'sr');
        $box      = $box_join->hydrate($row, 'sb');
        $category = $category_join->hydrate($row, 'c');

        $box->setCategory($category);
        $return->setSubmissionBox($box);

        if ($row['file.hash']) {
            $storage  = $storage_join->hydrate($row, 'file');
            $mime     = $mime_join->hydrate($row, 'mime');

            $storage->setMimetype($mime);
            $return->setStorage($storage);
        }

        return $return;
    }

    /**
     * Retrieves the number of returns that have been made on each box in a
     * list.
     *
     * @param array $box_ids
     * @param int $user_id
     *
     * @return array The submission box ID as the key, the count as the value.
     */
    public function findCountsForSubmissionBoxes($box_ids, $user_id)
    {
        // Retrieve count of submission IDs from a list of IDs for a user
        if (empty($box_ids)) {
            return array();
        }
        $query = "
			SELECT sr.box_id, COUNT(*) as submission_count
			FROM {$this->getTable()} sr
			WHERE sr.owner = :user_id
			AND sr.box_id IN {$this->getArrayPlaceholders($box_ids, 'sb')}
			AND sr.deleted IS FALSE
			GROUP BY sr.box_id
		";
        $statement = $this->database->prepare($query);
        $params = $this->getArrayParameters($box_ids, 'sb');
        $params['user_id'] = $user_id;
        $statement->execute($params);

        $return = array();
        foreach ($statement as $row) {
            $return[$row['box_id']] = $row['submission_count'];
        }
        return $return;
    }

    /**
     * Post-save callback for returned work models.
     *
     * Performs the following actions:
     * * Copies file from temp location into a location accessible by this model
     * * Saves a task completion for this student, if there's a task related to
     *   the submission box.
     * * Saves the data to the remote data connector
     *
     * @param ReturnedWork $object
     * @param ReturnedWork $previous
     */
    public function postSave(ReturnedWork $object, $previous = null)
    {
        // Doesn't matter whether it's new or not - only whether the mark is
        // greater than 0.

        // Attempt to save a task instance entry for this item.
        // Because this is a teacher, the task needs to be marked as
        // complete regardless of whether it requires confirmation.
        // It should depend on the normalized mark, though.
        if ($object->getNormalizedMark() > 0) {
            $submission_box = $this->getJoinMapper('submission_box')->findById(
                $object->getSubmissionBox()->getId()
            );
            $task_instance_id = $submission_box->getTaskInstance()->getId();
            if (isset($task_instance_id)) {
                // Find most recent for this task.
                // If it's completed, don't mark again because the student has
                // already completed it.
                try {
                    $recent_completion = Task\InstanceCompletionMapper::getInstance()->findLatestForTaskAndStudent(
                        $task_instance_id,
                        $object->getOwner()->getId()
                    );
                    $is_already_completed = ($recent_completion->getCompletionStatus() == Task\InstanceCompletion::COMPLETION_STATUS_COMPLETE);
                } catch (Schoolbox\Mapper\Exception $e) {
                    $is_already_completed = false;
                }
                if (!$is_already_completed) {
                    $completion = new Task\InstanceCompletion();
                    $completion
                        ->setTaskInstance($submission_box->getTaskInstance())
                        ->setStudent($object->getOwner())
                        ->setCompletionDate(new \Alaress_Date_Format(new \DateTime('now')))
                        ->setCompletionStatus(Task\InstanceCompletion::COMPLETION_STATUS_COMPLETE);
                    Task\InstanceCompletionMapper::getInstance()->save($completion);
                }
            }
        }
        // Copy data to remote system
        $externalMarkbook = new \External_Markbook();
        $externalMarkbook->setMark($object);
    }



}
