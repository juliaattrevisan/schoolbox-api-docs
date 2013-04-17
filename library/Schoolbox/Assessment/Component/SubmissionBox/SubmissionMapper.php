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
use \External_User;

class SubmissionMapper extends Schoolbox\Mapper {

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
			'column'     => 'mime_type',
			'property'   => 'mimetype',
			'type'       => self::TYPE_STRING,
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
		$this->registerCallback('postSave', array($this, 'postSave'));
	}

	/**
	 * Gets the list of activities related to student submissions.
	 *
	 * An activity is returned for each submission that this user has made into
	 * a submission box in the given categories.
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
			LEFT JOIN {$box_join->getJoinSql('category', 'sb', 'c')}
			WHERE sb.category IN {$box_join->getArrayPlaceholders($categories, 'category')}
			AND ss.owner = :user_id
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
	 * Returns an activity collection for the given user and submission box.
	 *
	 * @param $user_id
	 * @param $box_id
	 *
	 * @return ActivityCollection
	 */
	public function getSubmissionHistory($user_id, $box_id) {
		//
		// Needs filter on submission box number.
		// getActivities($user_id, array('box' => $box_id)
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
			LEFT JOIN {$box_join->getJoinSql('category', 'sb', 'c')}
			WHERE sb.id = :box_id
			AND ss.owner = :user_id
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
	 * @param array $row
	 *
	 * @return Submission
	 */
	protected function hydrateForActivityList($row) {

		$box_join      = $this->getJoinMapper('submission_box');
		$category_join = $box_join->getJoinMapper('category');

		$submission = $this->hydrate($row, 'ss');
		$box        = $box_join->hydrate($row, 'sb');
		$category   = $category_join->hydrate($row, 'c');
		$box->setCategory($category);
		$submission->setSubmissionBox($box);
		return $submission;
	}


	/**
	 * Copies file from temp location into a location accessible by this model.
	 *
	 * @param Submission $object
	 */
	public function postSave(Submission $object) {
		$object->copyFromTempLocation();
	}


	/**
	 * Port of SubmissionStudent handler's securityCheck() method.
	 *
	 * @throws Schoolbox\Resource\Exception
	 *
	 * @param Submission $submission
	 *
	 * @return bool
	 */
	public function checkUploadability(Submission $submission) {

		//
		// Are online submissions accepted?
		$submission_box = $submission->getSubmissionBox();
		if (!$submission_box->getAcceptOnline()) {
			throw new Schoolbox\Resource\Exception('This submission box does not accept online submission.');
		}

		//
		// Does a submission already exist for this student?
		$box_id          = $submission->getSubmissionBox()->getId();
		$user_id         = $submission->getOwner()->getId();
		$count_statement = $this->database->prepare("SELECT id FROM {$this->getTable()} WHERE box_id=? AND owner=?");
		$count_statement->execute(array($box_id, $user_id));
		$submitted = ($count_statement->rowCount() > 0);
		if ($submitted && !$submission_box->getAcceptMultiple()) {
			throw new Schoolbox\Resource\Exception('This submission box does not accept multiple submissions.');
		}

		//
		// Is the submission late (after overdue time)?
		$now = new \DateTime('now');
		if ($submission_box->getLateDate()->getUnixTimestamp() < $now->getTimestamp()) {
			throw new Schoolbox\Resource\Exception('This submission box no longer accepts submissions.');
		}

		//
		// Is the student involved in this group?
		// (Need much better checking than this, this abuses session variables)
		$cat_mapper = $this->getJoinMapper('submission_box')->getJoinMapper('category');
		$cat_mapper->setExternalUser(new External_User());
		$categories = array();
		foreach ($cat_mapper->findForUser($submission->getOwner(), true) as $cat) {
			$categories[] = $cat->getId();
		}
		if (!in_array($submission_box->getCategory()->getId(), $categories)) {
			//
			// 403 forbidden error
			throw new Schoolbox\Resource\Exception('Access denied');
		}

		//
		// Is the submission early (before open time)?
		if ($submission_box->getOpenDate()->getUnixTimestamp() > $now->getTimestamp()) {
			throw new Schoolbox\Resource\Exception('This submission box does not accept submissions yet.');
		}
		return true;
	}
}

