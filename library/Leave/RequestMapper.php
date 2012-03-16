<?php

class Leave_RequestMapper extends Mapper {
    protected $userMapper;
	protected $limit = null;

    protected function __construct () {
        $this->setUserMapper( NewUserMapper::getInstance() );

		/* Set the threshold for displaying/using lists of requests.
		 * Only show the last 14 days' worth of info
		 */
		$this->setLimit(date('Y-m-d H:i:s', strtotime('-14 days')));

        parent::__construct();
    }

    protected function mapping () {
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => Mapper::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'     => 'user_id',
            'property'   => 'userId',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'start',
            'property'   => 'start',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'finish',
            'property'   => 'finish',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'host_type',
            'property'   => 'hostType',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'host_name',
            'property'   => 'hostName',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'host_phone',
            'property'   => 'hostPhone',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'user_phone',
            'property'   => 'userPhone',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'description',
            'property'   => 'description',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'created',
            'property'   => 'created',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'status',
            'property'   => 'status',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'authorisation_datetime',
            'property'   => 'authorisationDatetime',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'authorisation_user_id',
            'property'   => 'authorisationUserId',
            'type'       => Mapper::TYPE_INTEGER,
        ));

		$this->hasMap(array(
			'column' => 'notes',
			'property' => 'notes',
			'type' => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column' => 'confirmed_by',
			'property' => 'confirmedBy',
			'type' => Mapper::TYPE_INTEGER,
		));

        parent::mapping();
    }

    /**
     * Returns a collection of requests by user id
     *
     * @param $userId
     * @return Collection
     */
    public function findAllByUserId ($userId) {
        $statement = $this->database->prepare("
            SELECT {$this->getColumnString('lr')},
              u.id AS 'u.id', u.username AS 'u.username', u.password AS 'u.password', u.group_id AS 'u.group_id',
              u.title AS 'u.title', u.firstname AS 'u.firstname', u.lastname AS 'u.lastname',
              u.email_pass AS 'u.email_pass',u.synergy_id AS 'u.synergy_id', u.campus AS 'u.campus', u.year AS 'u.year',
              u.house AS 'u.house', u.local_email AS 'u.local_email', u.alt_email AS 'u.alt_email',
              u.mail_handler AS 'u.mail_handler', u.hash AS 'u.hash', u.key AS 'u.key',
              u.eportfolio_enabled AS 'u.eportfolio_enabled',
              au.id AS 'au.id', au.username AS 'au.username', au.password AS 'au.password', au.group_id AS 'au.group_id',
              au.title AS 'au.title', au.firstname AS 'au.firstname', au.lastname AS 'au.lastname',
              au.email_pass AS 'au.email_pass',au.synergy_id AS 'au.synergy_id', au.campus AS 'au.campus', au.year AS 'au.year',
              au.house AS 'au.house', au.local_email AS 'au.local_email', au.alt_email AS 'au.alt_email',
              au.mail_handler AS 'au.mail_handler', au.hash AS 'au.hash', au.key AS 'au.key',
              au.eportfolio_enabled AS 'au.eportfolio_enabled'
            FROM `{$this->table}` lr
            LEFT JOIN `user` u ON u.id = lr.user_id
            LEFT JOIN `user` au ON au.id = lr.authorisation_user_id
            WHERE lr.user_id = :userId
        ");

        $statement->execute( array(
            'userId' => $userId
        ) );

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
     * @return Collection
     */
    public function findAllByHouseId ($houseId) {
        $statement = $this->database->prepare("
            SELECT {$this->getColumnString('lr')},
                {$this->getUserMapper()->getColumnString('u')},
                {$this->getUserMapper()->getColumnString('au')}
            FROM `{$this->table}` lr
            LEFT JOIN `user` u ON u.id = lr.user_id
            LEFT JOIN `user` au ON au.id = lr.authorisation_user_id
            WHERE u.residential_house_id = :houseId
            AND lr.created >= :limit
        ");

        $statement->execute( array(
            'houseId' => $houseId,
			'limit' => $this->getLimit(),
        ) );

        $requestList = $statement->fetchAll();

        $requestCollection = $this->hydrateList($requestList);

        return $requestCollection;
    }

	/**
	 * Returns the next pending request by house id
	 *
	 * @param $requestId
	 * @param $houseId
	 *
	 * @return Collection
	 */
	public function findPreviousPendingByHouseId($requestId, $houseId) {
		$statement = $this->database->prepare("
			SELECT {$this->getColumnString('lr')},
				{$this->getUserMapper()->getColumnString('u')}
			FROM `{$this->table}` lr
			LEFT JOIN `user` u ON u.id = lr.user_id
			WHERE u.house = :houseId
			AND lr.status = :status
			AND lr.id < :requestId
			AND lr.created >= :limit
			ORDER BY lr.id ASC
		");

		$statement->execute(array(
			'houseId' => $houseId,
			'requestId' => $requestId,
			'status' => 1,
			'limit' => $this->getLimit(),
		));

		$requestList = $statement->fetchAll();

		$requestCollection = $this->hydrateList($requestList);

		return $requestCollection[count($requestCollection)-1];
	}

	/**
	 * Returns the next pending request by house id
	 *
	 * @param $requestId
	 * @param $houseId
	 *
	 * @return Collection
	 */
	public function findNextPendingByHouseId($requestId, $houseId) {
		$statement = $this->database->prepare("
			SELECT {$this->getColumnString('lr')},
				{$this->getUserMapper()->getColumnString('u')}
			FROM `{$this->table}` lr
			LEFT JOIN `user` u ON u.id = lr.user_id
			WHERE u.house = :houseId
			AND lr.status = :status
			AND lr.id > :requestId
			AND lr.created >= :limit
			ORDER BY lr.id ASC
		");

		$statement->execute(array(
			'houseId' => $houseId,
			'requestId' => $requestId,
			'status' => 1,
			'limit' => $this->getLimit(),
		));

		$requestList = $statement->fetchAll();

		$requestCollection = $this->hydrateList($requestList);

		return $requestCollection[0];
	}

	/**
     * Returns all users in a house and any requests that intersect a date range defined by $start and $finish.
     * Returns users regardless of whether they have any requests.
     *
     * @param Integer $houseId
     * @param String $start
     * @param String $finish
     * @return Collection
     */
    public function getHouseReport ($houseId, $start, $finish) {
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
              /*AND g.type = 'student'*/
        ");

        $statement->execute( array(
            ':houseId' => $houseId,
            ':start' => $start,
            ':finish' => $finish,
        ) );

        $requestList = $statement->fetchAll();

        $requestCollection = $this->hydrateList($requestList);

        return $requestCollection;
    }

	/**
	 * Get the numbers of people who are absent and present for a given day, taking
	 * into account the days they have been scheduled to be away.
	 *
	 * To prevent shortages of fud, the following preconditions have been used.
	 *
	 * When Leaving:
	 * If you are present for any portion of a particular segment, you are classified
	 * as being present for that segment. e.g. If you leave between 00;00:00 and 11:59:59
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
    public function getCateringReport ($date) {
		if (!is_a($date, 'Alaress_Date_Format')) {
			$date = new Alaress_Date_Format($date, 'd/m/y');
		}

		$morningStart = $date;

		$dayStart = new Alaress_Date_Format($morningStart->getUnixTimestamp() + 12*60*60-1, 'U');
		$nightStart = new Alaress_Date_Format($dayStart->getUnixTimestamp() + 5*60*60, 'U');
		$nightEnd = new Alaress_Date_Format($nightStart->getUnixTimestamp() + 7*60*60, 'U');

		/* Mega query
		 */
		$statement = $this->database->prepare("
			SELECT
				luResidentialHouse.id AS houseId,
				luResidentialHouse.name AS name,
				leave_request.id AS leaveRequestId,
				SUM(leave_request.id AND ((`start` < :morningStart) AND (`finish` > :dayStart))) AS absent_morning,
				((SELECT COUNT(*) FROM `user` WHERE user.residential_house_id = houseId) - COALESCE(SUM(leave_request.id AND ((`start` < :morningStart) AND (`finish` > :dayStart))),0)) AS present_morning,
				SUM(leave_request.id AND ((`start` < :dayStart) AND (`finish` > :nightStart))) AS absent_day,
				((SELECT COUNT(*) FROM `user` WHERE user.residential_house_id = houseId) - COALESCE(SUM(leave_request.id AND ((`start` < :dayStart) AND (`finish` > :nightStart))),0)) AS present_day,
				SUM(leave_request.id AND ((`start` < :nightStart) AND (`finish` > :nightEnd))) AS absent_night,
				((SELECT COUNT(*) FROM `user` WHERE user.residential_house_id = houseId) - COALESCE(SUM(leave_request.id AND ((`start` < :nightStart) AND (`finish` > :nightEnd))),0)) AS present_night
			FROM luResidentialHouse
			LEFT JOIN `user` ON user.residential_house_id = luResidentialHouse.id
			LEFT JOIN leave_request ON leave_request.user_id = `user`.id AND leave_request.authorisation_datetime IS NOT NULL
			GROUP BY residential_house_id;
		");

		$statement->execute(array(
			':morningStart' => $morningStart->getDatabase(),
			':dayStart' => $dayStart->getDatabase(),
			':nightStart' => $nightStart->getDatabase(),
			':nightEnd' => $nightEnd->getDatabase(),
		));

		$houseList = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $houseList;
    }

    public function findById ($id) {
        $statement = $this->database->prepare("
            SELECT {$this->getColumnString('lr')},
                {$this->getUserMapper()->getColumnString('u')},
                {$this->getUserMapper()->getColumnString('au')}
            FROM `{$this->table}` lr
            LEFT JOIN `user` u ON u.id = lr.user_id
            LEFT JOIN `user` au ON au.id = lr.authorisation_user_id
            WHERE lr.id = :id
        ");

        $statement->execute( array(
            'id' => $id
        ) );

        $requestList = $statement->fetchAll();

        $requestCollection = $this->hydrateList($requestList);

        return $requestCollection[0];
    }

    public function findAllByDateRange ($start, $finish) {
        $statement = $this->database->prepare("
            SELECT {$this->getColumnString('lr')},
                {$this->getUserMapper()->getColumnString('u')},
                {$this->getUserMapper()->getColumnString('au')}
            FROM `{$this->table}` lr
            LEFT JOIN `user` u ON u.id = lr.user_id
            LEFT JOIN `user` au ON au.id = lr.authorisation_user_id
            WHERE lr.start < :finish && lr.finish > :start
        ");

        $statement->execute( array(
            ':start' => $start,
            ':finish' => $finish,
        ) );

        $requestList = $statement->fetchAll();

        $requestCollection = $this->hydrateList($requestList);

        return $requestCollection;
    }

    public function hydrateList ($rowList, $alias = '', $callback = null) {
        if ( empty($alias) ) {
            $alias = 'lr';
        }

        if ( empty($callback) ) {
            $callback = function ($mapper, $request, $row) {
                /* Hydrate user */
                if ( !empty($row['u.id']) ) {
                    $request->setUser( $mapper->getUserMapper()->hydrate($row, 'u') );
                }

                /* Hydrate authorized user */
                if ( !empty($row['au.id']) ) {
                    $request->setAuthorisationUser( $mapper->getUserMapper()->hydrate($row, 'au') );
                }

                return $request;
            };
        }

        $requestList = parent::hydrateList($rowList, $alias, $callback);

        return $requestList;
    }

    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
    }

    public function getUserMapper()
    {
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
