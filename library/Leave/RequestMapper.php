<?php

use Leave\HostTypeMapper;
use Schoolbox\UserMapper;

class Leave_RequestMapper extends Schoolbox\Mapper {
	protected $userMapper;
	protected $limit = null;

	protected function setup() {
		parent::setup();
		$this->setUserMapper(\Schoolbox\UserMapper::getInstance());

		/* Set the threshold for displaying/using lists of requests.
		   * Only show the last 14 days' worth of info
		   */
		$this->setLimit(date('Y-m-d H:i:s', strtotime('-14 days')));
	}

	protected function mapping() {
		$this->hasTable('leave_request');
        $this->hasModel(Leave_Request::class);
		$this->hasMap(
			array(
				'column' => 'id',
				'property' => 'id',
				'type' => self::TYPE_INTEGER,
				'identifier' => true
			)
		);

		$this->hasMap(
			array(
				'column' => 'user_id',
				'property' => 'user',
				'type' => self::TYPE_MODEL,
				'join' => 'user',
			)
		);

		$this->hasMap(
			array(
				'column' => 'author_id',
				'property' => 'author',
				'type' => self::TYPE_MODEL,
				'join' => 'author',
			)
		);

		$this->hasMap(
			array(
				'column' => 'start',
				'property' => 'start',
				'type' => self::TYPE_DATETIME,
			)
		);

		$this->hasMap(
			array(
				'column' => 'finish',
				'property' => 'finish',
				'type' => self::TYPE_DATETIME,
			)
		);

		$this->hasMap(
			array(
				'column' => 'host_type',
				'property' => 'hostType',
				'type' => self::TYPE_MODEL,
				'join' => 'host_type',
				'join_def' => array(
					'mapper'    => HostTypeMapper::class,
					'other_key' => 'id',
				),
			)
		);

		$this->hasMap(
			array(
				'column' => 'host_name',
				'property' => 'hostName',
				'type' => self::TYPE_STRING,
			)
		);

		$this->hasMap(
			array(
				'column' => 'host_phone',
				'property' => 'hostPhone',
				'type' => self::TYPE_STRING,
			)
		);

		$this->hasMap(
			array(
				'column' => 'host_mobile',
				'property' => 'hostMobile',
				'type' => self::TYPE_STRING
			)
		);

		$this->hasMap(
			array(
				'column' => 'host_address',
				'property' => 'hostAddress',
				'type' => self::TYPE_STRING
			)
		);

		$this->hasMap(
			array(
				'column' => 'collector_name',
				'property' => 'collectorName',
				'type' => self::TYPE_STRING
			)
		);

		$this->hasMap(
			array(
				'column' => 'returner_name',
				'property' => 'returnerName',
				'type' => self::TYPE_STRING
			)
		);

		$this->hasMap(
			array(
				'column' => 'reason',
				'property' => 'reason',
				'type' => self::TYPE_STRING
			)
		);

		$this->hasMap(
			array(
				'column' => 'user_phone',
				'property' => 'userPhone',
				'type' => self::TYPE_STRING,
			)
		);

		$this->hasMap(
			array(
				'column' => 'description',
				'property' => 'description',
				'type' => self::TYPE_STRING,
			)
		);

		$this->hasMap(
			array(
				'column' => 'created',
				'property' => 'created',
				'type' => self::TYPE_STRING,
			)
		);

		$this->hasMap(
			array(
				'column' => 'status',
				'property' => 'status',
				'type' => self::TYPE_INTEGER,
			)
		);

		$this->hasMap(
			array(
				'column' => 'authorisation_datetime',
				'property' => 'authorisationDatetime',
				'type' => self::TYPE_STRING,
			)
		);

		$this->hasMap(
			array(
				'column' => 'authorisation_user_id',
				'property' => 'authorisationUser',
				'type' => self::TYPE_MODEL,
				'join' => 'authorisationUser',
			)
		);

		$this->hasMap(
			array(
				'column' => 'travel_type_id',
				'property' => 'travelType',
				'type' => self::TYPE_MODEL,
				'join' => 'travelType',
			)
		);

		$this->hasMap(
			array(
				'column' => 'notes',
				'property' => 'notes',
				'type' => self::TYPE_STRING,
			)
		);

		$this->hasMap(
			array(
				'column' => 'confirmed_by',
				'property' => 'confirmedBy',
				'type' => self::TYPE_INTEGER,
			)
		);

		$this->hasJoin(
			'authorisationUser',
			array(
				'mapper'    => UserMapper::class,
				'this_key'  => 'authorisation_user_id',
				'other_key' => 'id',
			)
		);
		$this->hasJoin(
			'user',
			array(
				'mapper'    => UserMapper::class,
				'this_key'  => 'user_id',
				'other_key' => 'id',
			)
		);
		$this->hasJoin(
			'author',
			array(
				'mapper'    => UserMapper::class,
				'this_key'  => 'author_id',
				'other_key' => 'id',
			)
		);
		$this->hasJoin(
			'travelType',
			array(
				'mapper'    => Leave_Request_TravelTypeMapper::class,
				'this_key'  => 'travel_type_id',
				'other_key' => 'id',
			)
		);
	}

	/**
	 * Helper method for getting a base query (includes SELECT part).
	 *
	 * It joins this table against the user, authorisationUser and travelType
	 * joins.
	 *
	 * @return string
	 */
	protected function getBaseQuery() {
		return "
			SELECT
				{$this->getColumnString('lr')},
				{$this->getJoinColumnString('user', 'u')},
				{$this->getJoinColumnString('author', 'a')},
				{$this->getJoinColumnString('authorisationUser', 'au')},
				{$this->getJoinColumnString('travelType', 'tt')},
				{$this->getJoinColumnString('host_type', 'ht')}
			FROM `{$this->table}` lr
			LEFT JOIN {$this->getJoinSql('user', 'lr', 'u')}
			LEFT JOIN {$this->getJoinSql('author', 'lr', 'a')}
			LEFT JOIN {$this->getJoinSql('authorisationUser', 'lr', 'au')}
			LEFT JOIN {$this->getJoinSql('travelType', 'lr', 'tt')}
			LEFT JOIN {$this->getJoinSql('host_type', 'lr', 'ht')}
		";
	}

	/**
	 * Returns a collection of requests by user id
	 *
	 * @param $userId
	 * @return \Schoolbox\Collection
	 */
	public function findAllByUserId($userId) {
		$statement = $this->database->prepare("
			{$this->getBaseQuery()}
			WHERE lr.user_id = :userId
			AND lr.status != :status
			ORDER BY lr.start
		");

		$statement->execute(
			array(
				':userId' => $userId,
				':status' => Leave_Request::STATUS_DELETED,
			)
		);

		$requestList = $statement->fetchAll();

		$requestCollection = $this->hydrateList($requestList);

		return $requestCollection;
	}

	/**
	 * Returns a collection of requests by house id
	 *
	 * Only show the most recent fortnight's worth of requests
	 *
	 * @param $houseId
	 * @return \Schoolbox\Collection|Leave_Request[]
	 */
	public function findAllByHouseId($houseId) {

		/* This is the original query I used, which is sorted by year, surname and created date

		  $statement = $this->database->prepare("
			  SELECT
				  {$this->getColumnString('lr')},
				  {$this->getUserMapper()->getColumnString('u')},
				  {$this->getUserMapper()->getColumnString('au')}
			  FROM `{$this->table}` lr
			  LEFT JOIN `user` u ON u.id = lr.user_id
			  LEFT JOIN `user` au ON au.id = lr.authorisation_user_id
			  WHERE u.residential_house_id = :houseId
			  AND lr.created >= :limit
			  ORDER BY u.year, u.lastname, created DESC
		  ");
		  */

		$statement = $this->database->prepare("
			{$this->getBaseQuery()}
			WHERE u.residential_house_id = :houseId
			AND lr.created >= :limit
			AND lr.status != :deleted
			ORDER BY substr(lr.start, 1, 10), LENGTH(u.year), u.year, u.lastname
		");

		$statement->execute(
			array(
				':houseId' => $houseId,
				':limit' => $this->getLimit(),
				':deleted' => Leave_Request::STATUS_DELETED,
			)
		);

		$requestList = $statement->fetchAll();

		$requestCollection = $this->hydrateList($requestList);

		return $requestCollection;
	}

	/**
	 * Common function used for generating next/previous links
	 *
	 * Due to the multiple sorting requirement (year, surname, created) the resultant
	 * collection is in an indeterminate order. There seems to be no way to tell from
	 * the current request what the next and previous requests should be; e.g. an order
	 * could be as follows:
	 *
	 * id 60 (sam) created 2012-05-17 16:42:41
	 * id 58 (anita) created 2012-05-17 16:26:46
	 * id 59 (andrew) created 2012-05-17 16:27:26
	 * id 56 (bobby) 2012-05-17 13:16:11
	 *
	 * @param $houseId
	 * @return \Schoolbox\Collection
	 */
	private function getAllLeaveRequests($houseId) {
		$statement = $this->database->prepare("
			{$this->getBaseQuery()}
			WHERE u.residential_house_id = :houseId
			AND lr.status = :status
			AND lr.created >= :limit
			ORDER BY substr(lr.start, 1, 10), LENGTH(u.year), u.year, u.lastname
		");

		$statement->execute(
			array(
				'houseId' => $houseId,
				'status' => Leave_Request::STATUS_PENDING,
				'limit' => $this->getLimit(),
			)
		);

		$requestList = $statement->fetchAll();

		$requestCollection = $this->hydrateList($requestList);

		return $requestCollection;
	}

	/**
	 * Returns the previous pending request by house id
	 *
	 * @param $requestId
	 * @param $houseId
	 *
	 * @return Leave_Request
	 */
	public function findPreviousPendingByHouseId($requestId, $houseId) {
		$requestCollection = $this->getAllLeaveRequests($houseId);

		/* Manually iterate and get the next one.
		   */
		$previous = null;
		foreach ($requestCollection as $currentRequest) {
			if ($currentRequest->getId() == $requestId) {
				return $previous;
			}
			$previous = $currentRequest;
		}

		return null;
	}

	/**
	 * Returns the next pending request by house id
	 *
	 * @param $requestId
	 * @param $houseId
	 *
	 * @return Leave_Request
	 */
	public function findNextPendingByHouseId($requestId, $houseId) {
		$requestCollection = $this->getAllLeaveRequests($houseId);

		/* Manually iterate and get the next one.
		   */
		$next = false;
		foreach ($requestCollection as $currentRequest) {
			if ($next) {
				return $currentRequest;
			}
			if ($currentRequest->getId() == $requestId) {
				$next = true;
			}
		}

		return null;
	}

	/**
	 * Returns all users in a house and any requests that intersect a date range defined by $start and $finish.
	 * Returns users regardless of whether they have any requests.
	 *
	 * Uses a wacky pseudo-natural-sort method found here:
	 * http://stackoverflow.com/questions/153633/natural-sort-in-mysql
	 * ...in order to produce an ordered list of leave requests, ordered by surname within a year level descending
	 *
	 * @param Integer $houseId
	 * @param String $start
	 * @param String $finish
	 * @return \Schoolbox\Collection
	 */
	public function getHouseReport($houseId, $start, $finish) {

		/* The original query I used. LENGTH(u.year) simulates a natural sort

		$statement = $this->database->prepare("
			SELECT {$this->getColumnString('lr')},
				{$this->getUserMapper()->getColumnString('u')},
				{$this->getUserMapper()->getColumnString('au')}
			FROM `user` u
			LEFT JOIN groups g ON g.id = u.group_id
			LEFT JOIN `{$this->table}` lr ON lr.user_id = u.id
				AND lr.start < :finish
				AND lr.finish > :start
			LEFT JOIN `user` au ON au.id = lr.authorisation_user_id
			WHERE u.residential_house_id = :houseId
			ORDER BY LENGTH(u.year), u.year, u.lastname
		");
		*/

		// Right Join with filters onto users - I want all users in this
		// res.house, regardless of whether they have a leave request or not.
		$statement = $this->database->prepare("
			SELECT
				{$this->getColumnString('lr')},
				{$this->getUserMapper()->getColumnString('u')},
				{$this->getUserMapper()->getColumnString('au')},
				{$this->getJoinColumnString('travelType', 'tt')},
				{$this->getJoinColumnString('host_type', 'ht')}
			FROM {$this->getTable()} `lr`
			RIGHT JOIN {$this->getJoinSql('user', 'lr', 'u')}
				AND CAST(lr.start AS DATE) <= :finish
				AND lr.finish >= :start
				AND (lr.status = :statusApproved OR lr.status = :statusPending OR lr.status IS NULL)
			LEFT JOIN {$this->getJoinSql('authorisationUser', 'lr', 'au')}
			LEFT JOIN {$this->getJoinSql('travelType', 'lr', 'tt')}
			LEFT JOIN {$this->getJoinSql('host_type', 'lr', 'ht')}
			WHERE u.residential_house_id = :houseId
			AND u.deleted_at IS NULL
			ORDER BY LENGTH(u.year), u.year, u.lastname
		");

		$args = array(
			':houseId' => $houseId,
			':start' => $start,
			':finish' => $finish,
			':statusApproved' => Leave_Request::STATUS_APPROVED,
			':statusPending' => Leave_Request::STATUS_PENDING,
		);

		$statement->execute($args);

		$requestList = $statement->fetchAll();

		$requestCollection = $this->hydrateList($requestList);

		return $requestCollection;
	}

	/**
	 * Get the numbers of people who are absent and present for a given day, taking
	 * into account the days they have been scheduled to be away.
	 *
	 * To prevent shortages of food, the following preconditions have been used.
	 *
	 * When Leaving:
	 * If you are present for any portion of a particular segment, you are classified
	 * as being present for that segment. e.g. If you leave between 00:00:00 and 11:59:59
	 * you are still considered as being present for the morning.
	 *
	 * When Returning:
	 * If you are away for any portion of a particular segment, you are classified as
	 * being absent for that segment. e.g. If you return between 00:00:00 and 11:59:59,
	 * you are still considered as being away for the morning.
	 *
	 * @param $date
	 * @return array
	 */
	public function getCateringReport($date) {
		if (!is_a($date, 'Alaress_Date_Format')) {
			$date = Alaress_Date_Format::newLocalShort($date);
		}

		$breakfastStart = new Alaress_Date_Format(strtotime( $date->getDatabaseDate() .' '. Config::get('boarder_breakfast_start')), 'U');
		$breakfastEnd = new Alaress_Date_Format(strtotime($date->getDatabaseDate() .' '.  Config::get('boarder_breakfast_end')), 'U');

		$lunchStart = new Alaress_Date_Format( strtotime($date->getDatabaseDate() .' '.  Config::get('boarder_lunch_start')), 'U');
		$lunchEnd = new Alaress_Date_Format(strtotime($date->getDatabaseDate() .' '.  Config::get('boarder_lunch_end')), 'U');

		$dinnerStart = new Alaress_Date_Format(strtotime($date->getDatabaseDate() .' '.  Config::get('boarder_dinner_start')), 'U');
		$dinnerEnd = new Alaress_Date_Format( strtotime($date->getDatabaseDate() .' '.  Config::get('boarder_dinner_end')), 'U');

		/* Mega query
		   */
		$statement = $this->database->prepare(
			"
			SELECT
				luResidentialHouse.id AS houseId,
				luResidentialHouse.name AS name,
				leave_request.id AS leaveRequestId,
				SUM(leave_request.id AND ((`start` < :breakfastStart) AND (`finish` > :breakfastEnd))) AS absent_morning,
				((SELECT COUNT(*) FROM `user` WHERE `user`.residential_house_id = houseId AND `user`.deleted_at IS NULL) - COALESCE(SUM(leave_request.id AND ((`start` < :breakfastStart) AND (`finish` > :breakfastEnd))),0)) AS present_morning,
				SUM(leave_request.id AND ((`start` < :lunchStart) AND (`finish` > :lunchEnd))) AS absent_day,
				((SELECT COUNT(*) FROM `user` WHERE `user`.residential_house_id = houseId AND `user`.deleted_at IS NULL) - COALESCE(SUM(leave_request.id AND ((`start` < :lunchStart) AND (`finish` > :lunchEnd))),0)) AS present_day,
				SUM(leave_request.id AND ((`start` < :dinnerStart) AND (`finish` > :dinnerEnd))) AS absent_night,
				((SELECT COUNT(*) FROM `user` WHERE `user`.residential_house_id = houseId AND `user`.deleted_at IS NULL) - COALESCE(SUM(leave_request.id AND ((`start` < :dinnerStart) AND (`finish` > :dinnerEnd))),0)) AS present_night
			FROM luResidentialHouse
			INNER JOIN `user` ON `user`.residential_house_id = luResidentialHouse.id AND `user`.deleted_at IS NULL
            LEFT JOIN leave_request
                ON leave_request.user_id = `user`.id
                AND leave_request.authorisation_datetime IS NOT NULL
                AND leave_request.status NOT IN (:deleted, :rejected)
			GROUP BY residential_house_id
			ORDER BY luResidentialHouse.name
		"
		);

		$statement->execute(
			array(
				':breakfastStart' => $breakfastStart->getDatabase(),
				':breakfastEnd' => $breakfastEnd->getDatabase(),
				':lunchStart' => $lunchStart->getDatabase(),
				':lunchEnd' => $lunchEnd->getDatabase(),
				':dinnerStart' => $dinnerStart->getDatabase(),
				':dinnerEnd' => $dinnerEnd->getDatabase(),
                ':deleted' => Leave_Request::STATUS_DELETED,
                ':rejected' => Leave_Request::STATUS_REJECTED,
			)
		);

		$houseList = $statement->fetchAll(PDO::FETCH_ASSOC);

		return $houseList;
	}

	public function findById($id) {
		$statement = $this->database->prepare("
			{$this->getBaseQuery()}
			WHERE lr.id = :id
		");

		$statement->execute(
			array(
				'id' => $id
			)
		);

		$requestList = $statement->fetchAll();

		$requestCollection = $this->hydrateList($requestList);

		if ($requestCollection->count() === 0) {
			throw new Schoolbox\Mapper\Exception\NotFound();
		}

		return $requestCollection->first();
	}

	public function findAllByDateRange($start, $finish) {
		$statement = $this->database->prepare("
			{$this->getBaseQuery()}
			WHERE lr.start < :finish AND lr.finish > :start AND lt.status != :deleted
		");

		$statement->execute(
			array(
				':start' => $start,
				':finish' => $finish,
				':deleted' => Leave_Request::STATUS_DELETED,
			)
		);

		$requestList = $statement->fetchAll();

		$requestCollection = $this->hydrateList($requestList);

		return $requestCollection;
	}

	public function hydrateList($rowList, $alias = '', $callback = null) {
		if (empty($alias)) {
			$alias = 'lr';
		}

		if (empty($callback)) {
			$callback = function ($mapper, $request, $row) {
				/** @var self $mapper */
				/* Hydrate user */
				if (!$request instanceof Leave_Request) {
					$request = $mapper->createModel();
				}
				if (!empty($row['u.id'])) {
					$request->setUser($mapper->getUserMapper()->hydrate($row, 'u'));
				}
				if (!empty($row['a.id'])) {
					$request->setAuthor($mapper->getUserMapper()->hydrate($row, 'a'));
				}
				/* Hydrate authorized user */
				if (!empty($row['au.id'])) {
					$request->setAuthorisationUser($mapper->getUserMapper()->hydrate($row, 'au'));
				}
				/* Hydrate travel type */
				if (!empty($row['tt.id'])) {
					$request->setTravelType($mapper->getJoinMapper('travelType')->hydrate($row, 'tt'));
				}
				/* Hydrate request type */
				if (!empty($row['ht.id'])) {
					$request->setHostType($mapper->hydrateJoin($row, 'host_type', 'ht'));
				}
				return $request;
			};
		}

		$requestList = parent::hydrateList($rowList, $alias, $callback);

		return $requestList;
	}

	public function setUserMapper($userMapper) {
		$this->userMapper = $userMapper;
	}

	public function getUserMapper() {
		return $this->userMapper;
	}

	/**
	 * Set limit
	 *
	 * @param $limit
	 */
	private function setLimit($limit) {
		$this->limit = $limit;
	}

	/**
	 * Get limit
	 *
	 * @return null
	 */
	private function getLimit() {
		return $this->limit;
	}
}
