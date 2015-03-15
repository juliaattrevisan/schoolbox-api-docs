<?php
/**
 * Mapper for the Submission model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;
use Alaress_Date_Format;
use Schoolbox;
use Schoolbox\Assessment\ActivityFactory;
use Schoolbox\Assessment\ActivityCollection;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Collection;
use Schoolbox\File\Exception as FileException;
use Schoolbox\FileInterface;

/**
 * Class SubmissionMapper
 *
 * @deprecated in favour of separate Submission\File and Submission\Inline classes
 * @package Schoolbox\Assessment\Component\SubmissionBox
 */
class SubmissionMapper extends Schoolbox\Mapper implements Schoolbox\StorageMapperInterface {

	protected function mapping () {
		$this->hasTable('student_submission');
		$this->hasModel('\\Schoolbox\\Assessment\\Component\\SubmissionBox\\Submission');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'box_id',
			'property'   => 'submissionBox',
			'type'       => self::TYPE_MODEL,
			'join'       => 'submission_box',
		));
		$this->hasMap(array(
			'column'     => 'owner',
			'property'   => 'owner',
			'type'       => self::TYPE_MODEL,
			'join'       => 'owner',
		));
		$this->hasMap(array(
			'column'     => 'realname',
			'property'   => 'filename',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'submitted',
			'property'   => 'submitted',
			'type'       => self::TYPE_DATETIME,
		));
		$this->hasMap(array(
			'column'     => 'marked',
			'property'   => 'marked',
			'type'       => self::TYPE_BOOLEAN,
		));
		$this->hasMap(array(
			'column'     => 'late',
			'property'   => 'late',
			'type'       => self::TYPE_BOOLEAN,
		));
		$this->hasMap(array(
			'column'     => 'body',
			'property'   => 'body',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'draft',
			'property'   => 'draft',
			'type'       => self::TYPE_BOOLEAN,
		));
		$this->hasMap(array(
			'column'     => 'filesize',
			'property'   => 'filesize',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasJoin('submission_box', array(
			'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBoxMapper',
			'this_key'  => 'box_id',
			'other_key' => 'id',
		));
		$this->hasJoin('owner', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'owner',
			'other_key' => 'id',
		));
		$this->hasMap(array(
			'column'     => 'uuid',
			'property'   => 'uuid',
			'type'       => self::TYPE_STRING,
		));
		/* Relation to Allowed Files
		 */
		$hydrator = new Schoolbox\Column\Model();
		$hydrator->setIdentifierProperty('mime');
		$this->hasMap(array(
			'column'     => 'mime_type',
			'property'   => 'mimetype',
			'hydrator'   => $hydrator,
			'join'       => 'allowedFileType',
		));

		$this->hasJoin('allowedFileType', array(
			'mapper'    => '\\Schoolbox\\File\\MimetypeMapper',
			'this_key'  => 'mime_type',
			'other_key' => 'mime',
		));

        $this->registerCallback(self::CALLBACK_POSTSAVE, array($this, 'postSave'));
        $this->registerCallback(self::CALLBACK_POSTSAVE, array($this, 'createDefaultReturn'));
	}

	/**
	 * Gets the list of activities related to student submissions.
	 *
	 * An activity is returned for each submission that this user has made into
	 * a submission box in the given categories.
	 *
	 * @deprecated ported to Submission\AbstractMapper::getActivities()
	 *
	 * @param $user_id
	 * @param $limit
	 * @param $categories
	 *
	 * @return ActivityCollection
	 */
	public function getActivities($user_id, $limit, $categories) {
		$factory       = new ActivityFactory();
		$collection    = new ActivityCollection();
		$box_join      = $this->getJoinMapper('submission_box');
		$category_join = $box_join->getJoinMapper('category');
		$query = "
			SELECT
				{$this->getColumnString('ss')},
				{$box_join->getColumnString('sb')},
				{$category_join->getColumnString('c')}
			FROM {$this->getTable()} ss
			LEFT JOIN {$this->getJoinSql('submission_box', 'ss', 'sb')}
			LEFT JOIN {$this->getJoinSql('allowedFileType', 'ss', 'af')}
			LEFT JOIN {$box_join->getJoinSql('category', 'sb', 'c')}
			WHERE sb.category IN {$box_join->getArrayPlaceholders($categories, 'category')}
			AND ss.owner = :user_id
			AND ss.draft IS FALSE
			ORDER BY ss.submitted DESC
			LIMIT :limit
		";
		$statement = $this->database->prepare($query);
		$params    = $this->getArrayParameters($categories, 'category');
		$params['limit']   = $limit;
		$params['user_id'] = $user_id;
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
	 * Retrieves drafts for this user and box, ordered by most recent first.
	 * @deprecated ported to Submission\Inline::getDrafts()
	 *
	 * @param int $user_id
	 * @param int $box_id
	 * @param int $limit
	 *
	 * @return Collection
	 */
	public function getDrafts($user_id, $box_id, $limit = null) {
		$query = "
			SELECT {$this->getColumnString('ss')}
			FROM {$this->getTable()} ss
			WHERE ss.box_id = :box_id
			AND ss.owner = :user_id
			AND ss.draft IS TRUE
			ORDER BY ss.submitted DESC
		";
		if (isset($limit)) {
			$query .= ' LIMIT :limit';
		}
		$statement = $this->database->prepare($query);
		$statement->bindValue('user_id', $user_id, \PDO::PARAM_INT);
		$statement->bindValue('box_id',  $box_id,  \PDO::PARAM_INT);
		if (isset($limit)) {
			$statement->bindValue('limit', $limit, \PDO::PARAM_INT);
		}
		$statement->execute();
		return $this->hydrateList($statement, 'ss');
	}

	/**
	 * Returns an activity collection for the given user and submission box.
	 *
	 * @deprecated ported to Submission\AbstractMapper::getHistory()
	 *
	 * @param $user_id
	 * @param $box_id
	 *
	 * @return ActivityCollection
	 */
	public function getHistory($user_id, $box_id) {
		//
		// Needs filter on submission box number.
		// getActivities($user_id, array('box' => $box_id)
		$factory       = new ActivityFactory();
		$collection    = new ActivityCollection();
		$box_join      = $this->getJoinMapper('submission_box');
		$category_join = $box_join->getJoinMapper('category');
		$mime_join     = $this->getJoinMapper('allowedFileType');
		$query = "
			SELECT
				{$this->getColumnString('ss')},
				{$box_join->getColumnString('sb')},
				{$category_join->getColumnString('c')},
				{$mime_join->getColumnString('af')}
			FROM {$this->getTable()} ss
			LEFT JOIN {$this->getJoinSql('submission_box', 'ss', 'sb')}
			LEFT JOIN {$this->getJoinSql('allowedFileType', 'ss', 'af')}
			LEFT JOIN {$box_join->getJoinSql('category', 'sb', 'c')}
			WHERE sb.id = :box_id
			AND ss.owner = :user_id
			AND ss.draft IS FALSE
			ORDER BY ss.submitted DESC
		";
		$params = array(
			'user_id' => $user_id,
			'box_id'  => $box_id,
		);
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
	 * @deprecated ported to Submission\AbstractMapper::hydrateForActivityList()
	 *
	 * @param array $row
	 *
	 * @return Submission
	 */
	protected function hydrateForActivityList($row) {
		$box_join = $this->getJoinMapper('submission_box');
		$category_join = $box_join->getJoinMapper('category');
		$mime_join = $this->getJoinMapper('allowedFileType');

		$submission = $this->hydrate($row, 'ss');
		$box = $box_join->hydrate($row, 'sb');
		$category = $category_join->hydrate($row, 'c');
		$mime = $mime_join->hydrate($row, 'af');

		$box->setCategory($category);
		$submission->setSubmissionBox($box);
		$submission->setMimetype($mime);

		return $submission;
	}

	/**
	 * Post-save callback for Submission models.
	 *
	 * Peforms the following actions:
	 * * Copies file from temp location into a location accessible by this model
	 * * Saves a task completion for this submission's user, if there's a task
	 *   related to this submission box.
	 *
	 * @param Submission $object
	 * @param Submission $previous
	 */
	public function postSave(Submission $object, $previous = null) {
		$object->copyFromTempLocation();
		if (!$previous && !$object->getDraft()) {
			// Attempt to save a task instance entry for this item.
			$submission_box   = $this->getJoinMapper('submission_box')->findById($object->getSubmissionBox()->getId());
			$task_instance_id = $submission_box->getTaskInstance()->getId();
			if (isset($task_instance_id)) {
				Task\InstanceCompletionMapper::getInstance()->markAsCompleted(
					$task_instance_id, $object->getOwner()->getId()
				);
			}
		}
	}

    /**
     * Post-save callback.
     *
     * Attaches a default return to this submission for this student, if there
     * is no return for them already.
     *
     * @param Submission $object
     * @param Submission $previous
     */
    public function createDefaultReturn(Submission $object, $previous = null)
    {
        if (!($object->getDraft())) {
            $boxId = $object->getSubmissionBox()->getId();
            /** @var ReturnedWorkMapper $returnMapper */
            $returnMapper = $this->getJoinMapper('submission_box.return');
            if ($returnMapper->countByColumn('box_id', $boxId) === 0) {
                try {
                    // Get default state
                    $submissionStateMapper = new \DueWork_ErrorMapper();
                    $defaultState          = $submissionStateMapper->findDefaultForNewSubmissions();
                } catch (Schoolbox\Mapper\Exception $e) {
                    // No default state: do nothing
                    return;
                }
                /** @var ReturnedWork $defaultReturn */
                $defaultReturn = $returnMapper->createModel();
                $defaultReturn
                    ->setSubmissionBox($object->getSubmissionBox())
                    ->setOwner($object->getOwner())
                    // No real marker
                    ->setMarker($object->getSubmissionBox()->getOwner())
                    ->setMark($defaultState->getDisplayAs())
                    ->setReturned($object->getSubmitted())
                    ->setNormalizedMark($defaultState->getNormaliseAs())
                ;
                $returnMapper->save($defaultReturn);
            }
        }
    }

	/**
	 * Port of SubmissionStudent handler's securityCheck() method.
	 *
     * @deprecated this method belongs in SubmissionBoxMapper
	 * @throws Schoolbox\Resource\Exception
	 *
	 * @param Submission $submission
	 *
	 * @return bool
	 */
	public function checkUploadability(Submission $submission) {

        /** @var SubmissionBoxMapper $boxMapper */
        $boxMapper = $this->getJoinMapper('submission_box');

        $boxMapper->checkUploadability($submission->getSubmissionBox(), $submission->getOwner());
	}

    /**
     * Retrieves the number of submissions that have been made on each box in a
     * list.
     *
     * @param array $box_ids
     * @param int   $user_id
     *
     * @return array The submission box ID as the key, the count as the value.
     */
    public function findCountsForSubmissionBoxes($box_ids, $user_id) {
        // Retrieve count of submission IDs from a list of IDs for a user
        if (empty($box_ids)) {
            return array();
        }
        $query = "
            SELECT ss.box_id, COUNT(*) as submission_count
            FROM {$this->getTable()} ss
            WHERE ss.owner = :user_id
            AND ss.box_id IN {$this->getArrayPlaceholders($box_ids, 'sb')}
            AND ss.draft IS FALSE
            GROUP BY ss.box_id
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
     * Convenience method for creating and saving a file via this mapper.
     *
     * @param string $filename     the file's name.
     * @param string $tempLocation the location of the file to save.
     * @param int    $userId       the user to associate with the file.
     * @throws FileException       if the upload was not allowed.
     * @return FileInterface
     */
    public function upload($filename, $tempLocation, $userId)
    {
        $submission = new Submission();

        $submission
            ->setFilename($filename)
            ->setTempLocation($tempLocation)
            ->setSubmitted(Alaress_Date_Format::now())
            ->loadMimetypeFromFilename()
            ->setFilesize($submission->getFilesizeFromPath());

        if (!$submission->getMimetype()->isEnabled()) {
            throw new FileException('Invalid mime type', FileException::CODE_MIMETYPE_NOT_FOUND);
        }

        if ($submission->getMimetype()->getMaxFileSize() < $submission->getFilesize()) {
            throw new FileException('File too large', FileException::CODE_FILE_TOO_LARGE);
        }

        // TODO the record really SHOULD be saved here; but we don't have access to the box ID...

        return $submission;
    }

}

