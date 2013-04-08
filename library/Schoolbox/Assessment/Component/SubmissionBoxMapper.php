<?php
/**
 * Mapper for the SubmissionBox model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component;
use Schoolbox;
use Schoolbox\Assessment\ActivityFactory;
use Schoolbox\Assessment\ActivityCollection;

class SubmissionBoxMapper extends Schoolbox\Mapper {

	protected function mapping () {
		$this->hasTable('submission_box');
		$this->hasModel('\\Schoolbox\\Assessment\\Component\\SubmissionBox');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'category',
			'property'   => 'category',
			'type'       => self::TYPE_MODEL,
			'join'       => 'category',
		));
		$this->hasMap(array(
			'column'     => 'type',
			'property'   => 'workType',
			'type'       => self::TYPE_MODEL,
			'join'       => 'work_type',
		));
		$this->hasMap(array(
			'column'     => 'name',
			'property'   => 'name',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'description',
			'property'   => 'description',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'fid',
			'property'   => 'legacyFileId',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'owner',
			'property'   => 'owner',
			'type'       => self::TYPE_MODEL,
			'join'       => 'owner',
		));
		$this->hasMap(array(
			'column'     => 'open_date',
			'property'   => 'openDate',
			'type'       => self::TYPE_DATETIME,
		));
		$this->hasMap(array(
			'column'     => 'date',
			'property'   => 'dueDate',
			'type'       => self::TYPE_DATETIME,
		));
		$this->hasMap(array(
			'column'     => 'late_date',
			'property'   => 'lateDate',
			'type'       => self::TYPE_DATETIME,
		));
		$this->hasMap(array(
			'column'     => 'accept_submission',
			'property'   => 'acceptOnline',
			'type'       => self::TYPE_BOOLEAN,
		));
		$this->hasMap(array(
			'column'     => 'multi_submission',
			'property'   => 'acceptMultiple',
			'type'       => self::TYPE_BOOLEAN,
		));
		$this->hasMap(array(
			'column'     => 'created',
			'property'   => 'created',
			'type'       => self::TYPE_DATETIME,
			'created_timestamp' => true,
		));
		//
		// TODO add TYPE_COLLECTION
		$this->hasMap(array(
			'column'     => 'mark_type',
			'property'   => 'markType',
			'type'       => self::TYPE_MODEL,
			'join'       => 'mark_type',
		));
		$this->hasMap(array(
			'column'     => 'max_mark',
			'property'   => 'maxMark',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'rubric_result_set',
			'property'   => 'rubricResultSet',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'mark_visibility',
			'property'   => 'markVisibility',
			'type'       => self::TYPE_INTEGER,
		));

		//
		// adds listeners:
		// buildSelectQuery('\\Schoolbox\\Assessment\\Component\\SubmissionBox')
		// undefinedMethod('hardDelete', function() {hard delete})
		// $this->hasSoftDeleteField('deleted');

		$this->hasJoin('category', array(
			'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
			//
			// Define as mapper fields instead?
			// this_property:     property for model object
			// this_key_property: property for key (if there is one) if not, get
			//                    ID value from this_property
			// other_property:    property in mapped model in other object that
			//                    links to this model
			'this_key'  => 'category',
			'other_key' => 'id',

			'this_property'  => 'category',
			'this_property'  => 'category',
			'other_property' => 'id',
		));
		$this->hasJoin('files', array(
			'multiple'  => true,
			'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\FileMapper',
			'this_key'  => 'id',
			'other_key' => 'box_id',
		));

		$this->hasJoin('legacy_file', array(
			'multiple'  => true,
			'mapper'    => '\\Schoolbox\\Resource\\FileMapper',
			'this_key'  => 'fid',
			'other_key' => 'id',
		));

		$this->hasJoin('work_type', array(
			'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\WorkTypeMapper',
			'this_key'  => 'type',
			'other_key' => 'id',
		));

		$this->hasJoin('mark_type', array(
			'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\MarkTypeMapper',
			'this_key'  => 'mark_type',
			'other_key' => 'mark_type_id',
		));

		$this->hasJoin('owner', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'owner',
			'other_key' => 'id',
		));

		//
		// TODO add Mapper->hasCollection?
		$this->hasJoin('notify', array(
			'multiple'  => true,
			'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\NotificationMapper',
			'this_key'  => 'id',
			'other_key' => 'box_id',
		));
		$this->hasJoin('submission', array(
			'multiple'  => true,
			'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\SubmissionMapper',
			'this_key'  => 'id',
			'other_key' => 'box_id',
		));
		$this->hasJoin('return', array(
			'multiple'  => true,
			'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\ReturnedWorkMapper',
			'this_key'  => 'id',
			'other_key' => 'box_id',
		));

		$this
			->registerCallback('preSave',  array($this, 'preSave'))
			->registerCallback('postSave', array($this, 'postSave'))
		;
	}

	/**
	 * @param int  $id
	 * @param bool $include_deleted If true, then include soft-deleted items.
	 *
	 * @return SubmissionBox
	 */
	public function findById($id, $include_deleted = false) {
		$box_file_mapper = $this->getJoinMapper('files');
		$file_mapper     = $box_file_mapper->getJoinMapper('file');
		$notify_mapper   = $this->getJoinMapper('notify');
		$worktype_mapper = $this->getJoinMapper('work_type');
		$marktype_mapper = $this->getJoinMapper('mark_type');
		$query = "
			SELECT
				{$this->getColumnString('sb')},
				{$worktype_mapper->getColumnString('wt')},
				{$marktype_mapper->getColumnString('mt')}
			FROM {$this->getTable()} sb
			LEFT JOIN {$this->getJoinSql('work_type', 'sb', 'wt')}
			LEFT JOIN {$this->getJoinSql('mark_type', 'sb', 'mt')}
			WHERE sb.id=?
		";
		if (!$include_deleted) {
			$query .= ' AND sb.deleted IS FALSE';
		}
		$statement = $this->database->prepare($query);
		$statement->execute(array($id));

		$submission_box = false;

		if (($row = $statement->fetch()) !== false) {
			$submission_box = $this->hydrate($row, 'sb');
			$submission_box->setWorkType($worktype_mapper->hydrate($row, 'wt'));
			$submission_box->setMarkType($marktype_mapper->hydrate($row, 'mt'));
		}

		//
		// TODO get submission history for a user as part of submission (or of user?)

		if ($submission_box instanceof SubmissionBox) {
			foreach ($notify_mapper->findByColumn('box_id', $submission_box->getId()) as $notify) {
				$submission_box->addNotification($notify);
			}

			//
			// Get files? (This is more evidence separate file/link mapper isn't
			// required.)
			$query = "
				SELECT
					{$box_file_mapper->getColumnString('sm')},
					{$file_mapper->getColumnString('f')}
				FROM {$box_file_mapper->getTable()} sm
				LEFT JOIN {$box_file_mapper->getJoinSql('file', 'sm', 'f')}
				WHERE sm.box_id=?
			";
			$statement = $this->database->prepare($query);
			$statement->execute(array($id));
			foreach ($statement as $row) {
				$box_file       = $box_file_mapper->hydrate($row, 'sm');
				$box_file->setFile($file_mapper->hydrate($row, 'f'));
				$submission_box->addFileLink($box_file);
			}
		}
		return $submission_box;
	}

	/**
	 * Pre-save method for submission boxes - converts legacy file to a new
	 * storage file.
	 *
	 * @param SubmissionBox $object
	 *
	 * @return bool
	 */
	public function preSave(SubmissionBox $object) {
		if ($legacy_file_id = $object->getLegacyFileId()) {
			$old_file = $this->getJoinMapper('legacy_file')->findById($legacy_file_id);
			$new_file = $this->getJoinMapper('legacy_file')->copyToStorage($old_file);

			$new_link = $this->getJoinMapper('files')->createModel();
			$new_link->setFile($new_file);
			$object->getFileLinks()->add($new_link);

			//
			// Remove legacy file link because it is no longer needed.
			$object->setLegacyFileId(null);
		}
		return true;
	}

	/**
	 * Post-save method for submission boxes - saves new submission box files
	 * and notifications.
	 *
	 * @param SubmissionBox $object
	 *
	 * @return void
	 */
	public function postSave(SubmissionBox $object) {
		foreach ($object->getFileLinks() as $box_file) {
			if ($box_file->hasFile()) {
				$box_file->setSubmissionBoxId($object->getId());
				$this->getJoinMapper('files')->save($box_file);
			}
		}
		foreach ($object->getNotifications() as $notification) {
			$notification->setSubmissionBoxId($object->getId());
			$this->getJoinMapper('notify')->save($notification);
		}
	}

	/**
	 * @param SubmissionBox $object
	 * @param bool          $hard_delete
	 *
	 * @return bool
	 */
	public function delete($object, $hard_delete = false) {
		if ($hard_delete) {
			return parent::delete($object);
		}
		$query     = "UPDATE {$this->getTable()} SET deleted=TRUE WHERE id=?";
		$statement = $this->database->prepare($query);
		return $statement->execute(array($object->getId()));
	}


	/**
	 * Conversion of SubmissionBoxHandler->getAllYearsByUser().
	 *
	 * @param int $user_id The user ID to retrieve for.
	 *
	 * @return array
	 */
	public function getAllYearsByUser($user_id) {
		$query = "
			SELECT DISTINCT DATE_FORMAT(submitted, '%Y') AS submitted_year
			FROM student_submission
			WHERE owner = ?
			ORDER BY submitted DESC";

		$statement = $this->database->prepare($query);
		$statement->execute(array($user_id));

		$years = array();
		foreach ($statement as $row) {
			$years[] = $row['submitted_year'];
		}
		return $years;
	}


	public function getSemesterMonths($semester) {
		$month_start = 1;
		$month_end   = 6;
		if ($semester == 2) {
			$month_start = 7;
			$month_end   = 12;
		}
		return array('start' => $month_start, 'end' => $month_end);
	}


	/**
	 * Conversion of SubmissionBoxHandler->getAllYearsByUser().
	 *
	 * @todo move to a more appropriate class?
	 *
	 * This could get a list of categories which have submissions, and pass to
	 * CategoryMapper->getByIds()?
	 *
	 * @param int $user_id  The user ID to retrieve for.
	 * @param int $year     The year to fetch for.
	 * @param int $semester The semester to fetch for.
	 *
	 * @return array
	 */
	public function submittedToCategoryList($user_id, $year, $semester) {
		$query = "
			SELECT category.id, category.name, GROUP_CONCAT(cc.code) AS code
			FROM category
			LEFT JOIN categoryCode cc ON category.id = cc.categoryID
			WHERE category.id IN (
				SELECT DISTINCT sb.category
				FROM submission_box sb
				LEFT JOIN student_submission ss ON ss.box_id = sb.id
				LEFT JOIN submission_return  sr ON sr.box_id = sb.id
				WHERE (ss.owner = :user_id OR sr.owner = :user_id)
				AND (
					(YEAR(ss.submitted) = :year AND MONTH(ss.submitted) BETWEEN :month_start AND :month_end)
					OR
					(YEAR(sr.returned)  = :year AND MONTH(sr.returned)  BETWEEN :month_start AND :month_end)
				)
			)
			GROUP BY category.id
			ORDER BY category.name";

		$months    = $this->getSemesterMonths($semester);
		$statement = $this->database->prepare($query);
		$statement->execute(array(
			'user_id'     => $user_id,
			'year'        => $year,
			'month_start' => $months['start'],
			'month_end'   => $months['end'],
		));
		return $statement->fetchAll();
	}


	/**
	 * @todo complete this category loader.
	 *
	 * Requires: category and category code mapper
	 *
	 * @param     $user_id
	 * @param     $categories
	 * @param int $year
	 * @param int $semester
	 */
	public function getAllDueWork($user_id, $categories, $year = 0, $semester = 0) {
		//
		// Submission box: hydrate with category, student submissions and returns.
		$months = $this->getSemesterMonths($semester);
		$params = $this->getArrayParameters($categories, 'category');
		$params['year']    = $year ?: date('Y');
		$params['user_id'] = $user_id;
		$params['month_start'] = $months['start'];
		$params['month_end']   = $months['end'];
		//
		// IN conditions in prepared statements:
		$query = "
		    SELECT
			b.id AS bid,
			s.id AS sid,
			r.id AS rid,

			UNIX_TIMESTAMP(b.late_date) AS lateDate,
			UNIX_TIMESTAMP(b.date) AS dueDate,
			c.name AS categoryName,
			b.name,
			b.description,
			b.created, s.submitted, r.returned
			FROM submission_box b
			LEFT JOIN category c ON c.id = b.category
			LEFT JOIN student_submission s ON s.box_id = b.id AND (s.owner = :user_id OR s.owner IS NULL)
			LEFT JOIN submission_return  r ON r.box_id = b.id AND (r.owner = :user_id OR r.owner IS NULL)
			WHERE b.deleted = 0
			AND b.category IN {$this->getArrayPlaceholders($categories, 'category')}
			AND YEAR(b.date) = :year
			AND MONTH(b.date) BETWEEN :month_start AND :month_end
            GROUP BY b.id
			ORDER BY categoryName, b.created DESC, s.submitted DESC, r.returned DESC";

		//
		// Def not a fan of jamming everything into a single query which then
		// hydrates who knows how.
		// Try this:
		// select all categories by ID
		// select all submission boxes by student and category, group by category
		// select all student submissions, group by submission box
		// select all returns, group by submission box
		$statement = $this->database->prepare($query);
		$statement->execute($params);
		return $statement->fetchAll();

//		while ($row = $this->db->getNextArray($outerResult)) {
//			if ($row['rid']) {
//				$innerQuery = "
//					SELECT mark, comment
//					FROM submission_return
//					WHERE box_id = '{$row['bid']}'
//					AND owner = '$userID'
//					ORDER BY returned DESC
//					LIMIT 1
//				";
//				$collection[] = array_merge($row, $this->db->getRow($innerQuery));
//			} else {
//				$collection[] = $row;
//			}
//		}
//		return $collection;
	}

	/**
	 * Gets the list of activities related to submission boxes.
	 *
	 * The following activities are retrieved:
	 *
	 * * New submission boxes
	 * * Overdue submission boxes, which the user hasn't submitted work for
	 *   (The implementation in Submission->getActivityLog() appears to fetch
	 *    submission boxes which the user has submitted work for)
	 * * Closed submission boxes
	 *
	 * @param int   $user_id    The user for who activities are fetched for.
	 * @param int   $limit      The max number of activities to be fetched.
	 * @param array $categories The categories to fetch activities for.
	 *
	 * @return ActivityCollection
	 */
	public function getActivities($user_id, $limit, $categories) {
		$factory    = new ActivityFactory();
		$collection = new ActivityCollection();
		$queries = array(
			array(
				'event' => 'new',
				'query' => "
					SELECT
						{$this->getColumnString('sb')},
						{$this->getJoinMapper('category')->getColumnString('c')}
					FROM {$this->getTable()} sb
					LEFT JOIN {$this->getJoinSql('category', 'sb', 'c')}
					WHERE sb.category IN {$this->getArrayPlaceholders($categories, 'category')}
					AND sb.open_date < NOW()
					ORDER BY sb.open_date DESC
					LIMIT :limit
				",
				'date_field' => 'openDate',
				'limit'      => $limit,
			),
			array(
				//
				// Overdue: shouldn't this be for items which a student hasn't
				// submitted, instead of one which they have?
				'event' => 'overdue',
				'query' => "
					SELECT
						{$this->getColumnString('sb')},
						{$this->getJoinMapper('category')->getColumnString('c')}
					FROM {$this->getTable()} sb
					LEFT JOIN {$this->getJoinSql('category', 'sb', 'c')}
					LEFT JOIN {$this->getJoinSql('submission', 'sb', 'sm')} AND sm.owner = :user_id
					WHERE sb.category IN {$this->getArrayPlaceholders($categories, 'category')}
					AND sm.id IS NULL
					AND sb.date < NOW()
					ORDER BY sb.date DESC
					LIMIT :limit
				",
				'date_field' => 'dueDate',
				'limit'      => $limit,
				'user_id'    => $user_id,
			),
			array(
				'event' => 'closed',
				'query' => "
					SELECT
						{$this->getColumnString('sb')},
						{$this->getJoinMapper('category')->getColumnString('c')}
					FROM {$this->getTable()} sb
					LEFT JOIN {$this->getJoinSql('category', 'sb', 'c')}
					WHERE sb.category IN {$this->getArrayPlaceholders($categories, 'category')}
					AND sb.late_date < NOW()
					ORDER BY sb.late_date DESC
					LIMIT :limit
				",
				'date_field' => 'lateDate',
				'limit'      => $limit,
			),
		);

		foreach ($queries as $query) {
			$statement = $this->database->prepare($query['query']);
			$params    = $this->getArrayParameters($categories, 'category');
			if (!empty($query['limit'])) {
				$params['limit'] = $query['limit'];
			}
			if (!empty($query['user_id'])) {
				$params['user_id'] = $query['user_id'];
			}
			foreach ($params as $key => $value) {
				$statement->bindParam($key, intval($value), \PDO::PARAM_INT);
			}
			$statement->execute();

			foreach ($statement as $row) {
				$box = $this->hydrate($row, 'sb');
				$box->setCategory($this->getJoinMapper('category')->hydrate($row, 'c'));
				$collection->add($factory->buildFromSubmissionBox($box, $query['event']));
			}
		}

		return $collection;
	}

//	public function getSubmissionHistory() {
//
//	}
}
