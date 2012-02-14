<?php

class Leave_Controller extends Controller {
	const THRESHOLD_MORNING = '11:59:59';
	const THRESHOLD_DAY = '16:59:59';
	const THRESHOLD_NIGHT = '23:59:59';

	/* Show leave requests over RANGE days
	 * Change
	 */
	const RANGE = 3;

    protected $requestMapper;
    protected $userMapper;
    protected $houseMapper;
    protected $externalUser;
	protected $group;

    /**
     * Constructor
     *
     * @param Page $page
     */
    public function __construct ($page = null) {
        parent::__construct($page);

        $this->setUserMapper(NewUserMapper::getInstance());
        $this->setRequestMapper(Leave_RequestMapper::getInstance());
        $this->setHouseMapper(HouseMapper::getInstance());
        $this->setExternalUser(new External_User());
		$this->setGroup(new Group());
    }

    /**
     *
     *
     * @return Array
     */
    public function executeCreateRequest () {
        return array();
    }

    /**
     * Renders a create request page
     *
     * @param Array $data
     * @return void
     */
    public function renderCreateRequest ($data) {
        $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/createRequest.html');
		$this->getPage()->setBlock('custom_head', 'student_autocomplete_javascript', 'student_autocomplete_javascript_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'student_autocomplete', 'student_autocomplete_block');

        $this->getPage()->setVar($data);

		/* Add terms and conditions
		 */
		$this->getPage()->setVar(array(
			'TERMS_AND_CONDITIONS' => Cfg::get('terms_and_conditions')
		));

		/* Apply autocomplete if appropriate - staff can submit on someone else's behalf
		 */
		if ($this->getGroup()->isStaff()) {
			$this->getPage()->setVar(array(
				'STUDENT_GROUP_IDS' => implode(',', $this->getGroup()->getStudentGroupTypeIds())
			));

			$this->getPage()->parse('student_autocomplete_block', 'student_autocomplete');
			$this->getPage()->parse('student_autocomplete_javascript_block', 'student_autocomplete_javascript');
		}
		/* Everyone else submits as themselves
		 */
		else {
			$this->getPage()->setVar('student_autocomplete_block', '');
			$this->getPage()->setVar('student_autocomplete_javascript_block', '');
		}
    }

	/**
	 * Processes form data submitted from renderCreateRequest
	 *
	 * @param Array $data
	 *
	 * @return void
	 */
	public function processCreateRequest($data) {
		if (!is_array($data) || empty($data['request'])) {
			$this->getPage()->error(2);
		}

		$start = new Alaress_Date_Format("{$data['request']['start']['date']} {$data['request']['start']['hour']}:{$data['request']['start']['minute']} {$data['request']['start']['meridian']}", 'd/m/y');
		$finish = new Alaress_Date_Format("{$data['request']['finish']['date']} {$data['request']['finish']['hour']}:{$data['request']['finish']['minute']} {$data['request']['finish']['meridian']}", 'd/m/y');
		$created = new Alaress_Date_Format(time(), 'U');
		$request = new Leave_Request();

		/* Use the userId field when submitting on someone else's behalf,
		 * otherwise just your own id
		 */
		if ($this->getGroup()->isStaff()) {
			$request->setUserId($data['userId']);
		}
		else {
			$request->setUserId($_SESSION['id']);
		}

		$request->setStart($start->getDatabase());
		$request->setFinish($finish->getDatabase());
		$request->setHostType($data['request']['hostType']);
		$request->setHostName($data['request']['hostName']);
		$request->setHostPhone($data['request']['hostPhone']);
		$request->setUserPhone($data['request']['userPhone']);
		$request->setDescription($data['request']['description']);
		$request->setStatus(Leave_Request::STATUS_PENDING);
		$request->setCreated($created->getDatabase());

		$this->getRequestMapper()->save($request);

		$request = $this->getRequestMapper()->findById($request->getId());

		$this->sendParentNotice($request);

		$this->getPage()->backToTheFuture(-1);
	}

	/**
	 * Returns a collection of requests by house id
	 *
	 * @param Integer $houseId
	 *
	 * @return Array
	 */
	public function executeHouseRequestList($houseId) {
		$house = $this->getHouseMapper()->findById($houseId);
		$requestList = $this->getRequestMapper()->findAllByHouseId($house->getId());

		return array(
			'house' => $house,
			'requestList' => $requestList,
		);
	}

    /**
     * Renders a collection of requests by house
     *
     * @param Array $data
     * @return void
     */
	public function renderHouseRequestList($data) {
		$house = $data['house'];
		$requestList = $data['requestList'];

		$this->getPage()->setFile(TEMPLATE_content, 'modules/leave/houseRequestList.html');
		$this->getPage()->setBlock(TEMPLATE_content, 'no_requests', 'no_requests_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'pending_request_list', 'pending_request_list_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'previous_request_list', 'previous_request_list_block');
		$this->getPage()->setBlock('pending_request_list', 'pending_request', 'pending_request_block');
		$this->getPage()->setBlock('previous_request_list', 'previous_request', 'previous_request_block');

		$this->getPage()->setVar(array(
			'HOUSE_ID' => $house->getId(),
			'HOUSE_NAME' => $house->getHouse()
		));

		if (count($requestList)) {
			$this->getPage()->setVar('no_requests_block', '');

			$pendingRequestCount = 0;
			$previousRequestCount = 0;

			foreach ($requestList as $request) {
				$this->getPage()->setVar(array(
					'USER_ID' => $request->getUser()->getId(),
					'USER_NAME' => $request->getUser()->getName(),
					'USER_YEAR' => $request->getUser()->getYear(),
					'REQUEST_ID' => $request->getId(),
					'REQUEST_START' => $request->getStart(true)->getAustralian(),
					'REQUEST_DURATION' => $request->getDuration(),
					'REQUEST_HOST_TYPE' => $request->getHostType(true)
				));

				if ($request->getStatus() == Leave_Request::STATUS_PENDING) {
					$this->getPage()->setVar(array(
						'CLASS' => $pendingRequestCount % 2 ? '' : 'dark',
					));

					$this->getPage()->parse('pending_request_block', 'pending_request', true);

					$pendingRequestCount++;
				}
				else {
					$this->getPage()->setVar(array(
						'REQUEST_STATUS' => $request->getStatus(true),
						'REQUEST_AUTHORISATION_DATETIME' => $request->getAuthorisationDatetime(true)->getAustralian(),
						'REQUEST_AUTHORISATION_USER_ID' => $request->getAuthorisationUser()->getId(),
						'REQUEST_AUTHORISATION_USER_NAME' => $request->getAuthorisationUser()->getName(),
						'CLASS' => $previousRequestCount % 2 ? '' : 'dark',
					));

					$this->getPage()->parse('previous_request_block', 'previous_request', true);

					$previousRequestCount++;
				}
			}

			if ($pendingRequestCount) {
				$this->getPage()->parse('pending_request_list_block', 'pending_request_list');
			}
			else {
				$this->getPage()->setVar('pending_request_list_block', '');
			}

			if ($previousRequestCount) {
				$this->getPage()->parse('previous_request_list_block', 'previous_request_list');
			}
			else {
				$this->getPage()->setVar('previous_request_list_block', '');
			}
		}
		else {
			$this->getPage()->setVar('pending_request_list_block', '');
			$this->getPage()->setVar('previous_request_list_block', '');

			$this->getPage()->parse('no_requests_block', 'no_requests');
		}
	}

    /**
     * Returns a collection of requests for a user
     *
     * @param $userId
     * @return Collection
     */
	public function executeUserRequestList($userId) {
		$user = $this->getUserMapper()->findById($userId);
		$house = $this->getHouseMapper()->findById($user->getHouse());
		$requestList = $this->getRequestMapper()->findAllByUserId($user->getId());

		return array(
			'user' => $user,
			'house' => $house,
			'requestList' => $requestList,
		);
	}

    /**
     * Renders a collection of request for a user
     *
     * @param $data
     * @return void
     */
	public function renderUserRequestList($data) {
		$user = $data['user'];
		$house = $data['house'];
		$requestList = $data['requestList'];

		$this->getPage()->setFile(TEMPLATE_content, 'modules/leave/userRequestList.html');

		$this->getPage()->setBlock(TEMPLATE_content, 'no_requests', 'no_requests_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'pending_request_list', 'pending_request_list_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'previous_request_list', 'previous_request_list_block');
		$this->getPage()->setBlock('pending_request_list', 'pending_request', 'pending_request_block');
		$this->getPage()->setBlock('previous_request_list', 'previous_request', 'previous_request_block');

		$this->getPage()->setVar(array(
			'USER_ID' => $user->getId(),
			'USER_NAME' => $user->getName(),
			'HOUSE_NAME' => $house->getHouse(),
			'HOUSE_ID' => $house->getId()
		));

		if (count($requestList)) {
			$this->getPage()->setVar('no_requests_block', '');

			$pendingRequestCount = 0;
			$previousRequestCount = 0;

			foreach ($requestList as $request) {
				$this->getPage()->setVar(array(
					'USER_ID' => $request->getUser()->getId(),
					'USER_NAME' => $request->getUser()->getName(),
					'USER_YEAR' => $request->getUser()->getYear(),
					'REQUEST_ID' => $request->getId(),
					'REQUEST_START' => $request->getStart(true)->getAustralian(),
					'REQUEST_DURATION' => $request->getDuration(),
					'REQUEST_HOST_TYPE' => $request->getHostType(true)
				));

				if ($request->getStatus() == Leave_Request::STATUS_PENDING) {
					$this->getPage()->setVar(array(
						'CLASS' => $pendingRequestCount % 2 ? '' : 'dark',
					));

					$this->getPage()->parse('pending_request_block', 'pending_request', true);

					$pendingRequestCount++;
				}
				else {
					$this->getPage()->setVar(array(
						'REQUEST_STATUS' => $request->getStatus(true),
						'REQUEST_AUTHORISATION_DATETIME' => $request->getAuthorisationDatetime(true)->getAustralian(),
						'REQUEST_AUTHORISATION_USER_ID' => $request->getAuthorisationUser()->getId(),
						'REQUEST_AUTHORISATION_USER_NAME' => $request->getAuthorisationUser()->getName(),
						'CLASS' => $previousRequestCount % 2 ? '' : 'dark',
					));

					$this->getPage()->parse('previous_request_block', 'previous_request', true);

					$previousRequestCount++;
				}
			}

			if ($pendingRequestCount) {
				$this->getPage()->parse('pending_request_list_block', 'pending_request_list');
			}
			else {
				$this->getPage()->setVar('pending_request_list_block', '');
			}

			if ($previousRequestCount) {
				$this->getPage()->parse('previous_request_list_block', 'previous_request_list');
			}
			else {
				$this->getPage()->setVar('previous_request_list_block', '');
			}
		}
		else {
			$this->getPage()->parse('no_requests_block', 'no_requests');

			$this->getPage()->setVar('pending_request_list_block', '');
			$this->getPage()->setVar('previous_request_list_block', '');
		}
	}

    /**
     * Processes modifications against a list of requests
     *
     * @param Array $data
     * @return void
     */
	public function processModifyRequestList($data) {
		$user = $data['user'];
		$requestList = $data['requestList'];

		if (!empty($requestList)) {
			foreach ($requestList as $request) {
				$request = $this->getRequestMapper()->findById($request['id']);

				if (isset($data['approve']) || isset($data['reject'])) {
					$authorisationDatetime = new Alaress_Date_Format(time(), 'U');

					$request->setAuthorisationDatetime($authorisationDatetime->getDatabase());
					$request->setAuthorisationUserId($_SESSION['id']);

					if (isset($data['approve'])) {
						$request->setStatus(Leave_Request::STATUS_APPROVED);
					}
					elseif (isset($data['reject'])) {
						$request->setStatus(Leave_Request::STATUS_REJECTED);
					}
				}

				$this->getRequestMapper()->save($request);

				if (isset($data['approve']) || isset($data['reject'])) {
					$request = $this->getRequestMapper()->findById($request->getId());

					$this->sendUserNotice($request);
				}
			}
		}

		$this->getPage()->localRedir("/modules/leave/student/{$user['id']}");
	}

    /**
     * Returns a data array with the request to be modified
     *
     * @param Integer $requestId
     * @return Array
     */
	public function executeModifyRequest($requestId) {
		return array(
			'request' => $this->getRequestMapper()->findById($requestId)
		);
	}

    /**
     * Renders a modify request form
     *
     * @param Array $data
     * @return void
     */
	public function renderModifyRequest($data) {
		$request = $data['request'];
		$user = $request->getUser();
		$house = $this->getHouseMapper()->findById($user->getHouse());

		$this->getPage()->setFile(TEMPLATE_content, 'modules/leave/modifyRequest.html');

		$this->getPage()->setVar(array(
			'REQUEST_ID' => $request->getId(),
			'REQUEST_STATUS' => $request->getStatus(true),
			'USER_NAME' => $user->getName(),
			'USER_EXTERNAL_ID' => $user->getSynergyId(),
			'USER_YEAR' => $user->getYear(),
			'USER_HOUSE' => $house->getHouse(),
			'REQUEST_START_DATE' => $request->getStart(true)->getLocalShort(),
			'REQUEST_START_HOUR' => $request->getStart(true)->getUsingFormat('h'),
			'REQUEST_START_MINUTE' => $request->getStart(true)->getUsingFormat('i'),
			'REQUEST_FINISH_DATE' => $request->getFinish(true)->getLocalShort(),
			'REQUEST_FINISH_HOUR' => $request->getFinish(true)->getUsingFormat('h'),
			'REQUEST_FINISH_MINUTE' => $request->getFinish(true)->getUsingFormat('i'),
			'REQUEST_HOST_NAME' => $request->getHostName(),
			'REQUEST_HOST_PHONE' => $request->getHostPhone(),
			'REQUEST_USER_PHONE' => $request->getUserPhone(),
			'REQUEST_DESCRIPTION' => $request->getDescription(),
			'REQUEST_NOTES' => $request->getNotes(),
		));

		if ($request->getStart(true)->getUsingFormat('a') == 'am') {
			$this->getPage()->setVar('REQUEST_START_MERIDIAN_AM', 'selected="selected"');
		}
		else {
			$this->getPage()->setVar('REQUEST_START_MERIDIAN_PM', 'selected="selected"');
		}

		if ($request->getFinish(true)->getUsingFormat('a') == 'am') {
			$this->getPage()->setVar('REQUEST_FINISH_MERIDIAN_AM', 'selected="selected"');
		}
		else {
			$this->getPage()->setVar('REQUEST_FINISH_MERIDIAN_PM', 'selected="selected"');
		}
	}

    /**
     * Processes a modify form for a request
     *
     * @param Array $data
     * @return void
	 */
	public function processModifyRequest($data) {
		$request = $this->getRequestMapper()->findById($data['request']['id']);

		$start = new Alaress_Date_Format("{$data['request']['start']['date']} {$data['request']['start']['hour']}:{$data['request']['start']['minute']} {$data['request']['start']['meridian']}", 'd/m/y');
		$finish = new Alaress_Date_Format("{$data['request']['finish']['date']} {$data['request']['finish']['hour']}:{$data['request']['finish']['minute']} {$data['request']['finish']['meridian']}", 'd/m/y');

		$request->setStart($start->getDatabase());
		$request->setFinish($finish->getDatabase());
		$request->setHostType($data['request']['hostType']);
		$request->setHostName($data['request']['hostName']);
		$request->setHostPhone($data['request']['hostPhone']);
		$request->setUserPhone($data['request']['userPhone']);
		$request->setDescription($data['request']['description']);
		$request->setNotes($data['request']['notes']);

		if (isset($data['approve']) || isset($data['reject'])) {
			$authorisationDatetime = new Alaress_Date_Format(time(), 'U');

			$request->setAuthorisationDatetime($authorisationDatetime->getDatabase());
			$request->setAuthorisationUserId($_SESSION['id']);

			if (isset($data['approve'])) {
				$request->setStatus(Leave_Request::STATUS_APPROVED);
			}
			elseif (isset($data['reject'])) {
				$request->setStatus(Leave_Request::STATUS_REJECTED);
			}
		}

		$this->getRequestMapper()->save($request);

		$request = $this->getRequestMapper()->findById($request->getId());

		if (isset($data['approve']) || isset($data['reject'])) {
			$this->sendUserNotice($request);
		}

		$this->getPage()->backToTheFuture(2);
	}

	/**
	 * Get a list of leave requests between specified dates
	 *
	 * @param $houseId
	 * @param $date
	 * @return array
	 */
	public function executeHouseReport($houseId, $date) {
		if (!$house = $this->getHouseMapper()->findById($houseId)) {
			$this->getPage()->error(2);
		}

		/* Mess with these two magic numbers to change the range, in conjunction
		 * with the class constant RANGE
		 *
		 * TODO: Remove these magic numbers
		 */
		$reportStart = new Alaress_Date_Format($date, 'd/m/y');
		$reportStart->addDays(5 - $reportStart->getWeekDayNumber());

		$reportFinish = new Alaress_Date_Format($date, 'd/m/y');
		$reportFinish->addDays(7 - $reportFinish->getWeekDayNumber());

		$requestList = $this->getRequestMapper()->getHouseReport($houseId, $reportStart->getDatabaseDate(), $reportFinish->getDatabaseDate());

		$houseList = $this->getHouseMapper()->findAll();

        return array(
			'house' => $house,
			'date' => $date,
			'reportStart' => $reportStart,
			'reportFinish' => $reportFinish,
			'requestList' => $requestList,
			'houseList' => $houseList
        );
    }

	/**
	 * Render a list of Leave Requests for people in a particular House.
	 *
	 * @param $data
	 */
	public function renderHouseReport($data) {
        $currentHouse = $data['house'];
        $houseList = $data['houseList'];
        $requestList = $data['requestList'];

		/* Render output
		 */
        $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/houseReport.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'house', 'house_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'no_requests', 'no_requests_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'row', 'row_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'report_header', 'report_header_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'report_header_subtitle', 'report_header_subtitle_block');
        $this->getPage()->setBlock('row', 'request', 'request_block');
		$this->getPage()->setBlock('request', 'request_period', 'request_period_block');
        $this->getPage()->setBlock('row', 'empty_request', 'empty_request_block');

		if (count($houseList)) {
			foreach ($houseList as $house) {
				$this->getPage()->setVar(array(
                    'HOUSE_ID' => $house->getId(),
                    'HOUSE_NAME' => $house->getHouse()
                ) );

				if ($house->getId() == $currentHouse->getId()) {
                    $this->getPage()->setVar('HOUSE_SELECTED', 'selected="selected"');
                }
                else {
                    $this->getPage()->setVar('HOUSE_SELECTED', '');
                }

                $this->getPage()->parse('house_block', 'house', true);
            }
        }

		if (count($requestList)) {
			$this->getPage()->setVar('no_requests_block', '');

			/* Parse out the headings
			 */
			for ($i = 0; $i < self::RANGE; $i++) {
				$headerDate = new Alaress_Date_Format($data['reportStart']->getUnixTimestamp(), 'U');
				$headerDate->addDays($i);

				$this->getPage()->setVar(array(
					'REPORT_HEADER_DATE' => $headerDate->getShort(),
				));

				$this->getPage()->parse('report_header_block', 'report_header', true);
				$this->getPage()->parse('report_header_subtitle_block', 'report_header_subtitle', true);
			}

			/* Parse out the requests
			 */
			foreach ($requestList as $i => $request) {
				$this->getPage()->setVar(array(
					'USER_ID' => $request->getUser()->getId(),
					'USER_NAME' => $request->getUser()->getName(),
					'USER_YEAR' => $request->getUser()->getYear(),
					'CLASS' => $i % 2 ? '' : 'dark'
				));

				if ($request->getId()) {
					$this->getPage()->setVar(array(
						'REQUEST_ID' => $request->getId(),
						'REQUEST_STATUS' => $request->getStatus(true),
						'REQUEST_HOST_TYPE' => $request->getHostType(true),
					));

					/* Start and end of leave
					 */
					$requestDateStart = new Alaress_Date_Format($request['start'], 'y-m-d h:i:s');
					$requestDateFinish = new Alaress_Date_Format($request['finish'], 'y-m-d h:i:s');

					/* For each day that the person is on leave within the prescribed period of time, show an
					 * icon. If they are starting or ending their leave on this day, show the time as well
					 *
					 * Render 3 columns each day for 3 days
					 *
					 * Morning: 00:00:00 to 11:59:59
					 * Day: 12:00:00 to 17:59:59
					 * Night: 18:00:00 to 23:59:59
					 */
					$morningStart = new Alaress_Date_Format($data['reportStart']->getUnixTimestamp(), 'U');
					$dayStart = new Alaress_Date_Format($morningStart->getUnixTimestamp() + 12*60*60-1, 'U');
					$nightStart = new Alaress_Date_Format($dayStart->getUnixTimestamp() + 5*60*60, 'U');
					$nightEnd = new Alaress_Date_Format($nightStart->getUnixTimestamp() + 7*60*60, 'U');

					$showStartTime = true;

					for ($i = 0; $i < self::RANGE; $i++) {

						/* If this person is on leave during the morning
						 */
						if ($request->getOccupancy($morningStart, $dayStart)) {

							/* Check whether next segment is occupied; if not, also show the time
							 */
							if ($showStartTime) {

								/* Specifically check for this day, to account for multi-week leave
								 */
								if ($requestDateStart->getDatabaseDate() == $morningStart->getDatabaseDate()) {
									$requestPeriodMorning = "<span>L {$requestDateStart->getTimeOnly()}</span>";
								}
								else {
									$requestPeriodMorning = "<span>L</span>";
								}

								$showStartTime = false;
							}
							else if (!$showStartTime && !$request->getOccupancy($dayStart, $nightStart)) {
								$requestPeriodMorning = "<span>L {$requestDateFinish->getTimeOnly()}</span>";
							}
							else {
								$requestPeriodMorning = "<span>L</span>";
							}
						}

						/* If this person is on leave during the day
						 */
						if ($request->getOccupancy($dayStart, $nightStart)) {
							if ($showStartTime) {
								if ($requestDateStart->getDatabaseDate() == $dayStart->getDatabaseDate()) {
									$requestPeriodDay = "<span>L {$requestDateStart->getTimeOnly()}</span>";
								}
								else {
									$requestPeriodDay = "<span>L</span>";
								}

								$showStartTime = false;
							}
							else if (!$showStartTime && !$request->getOccupancy($nightStart, $nightEnd)) {
								$requestPeriodDay = "<span>L {$requestDateFinish->getTimeOnly()}</span>";
							}
							else {
								$requestPeriodDay = "<span>L</span>";
							}
						}

						/* If this person is on leave during the night
						 */
						if ($request->getOccupancy($nightStart, $nightEnd)) {

							/* Check whether the next morning is occupied
							 */
							$nextMorning = new Alaress_Date_Format($dayStart->getDatabase(), 'y-m-d h:i:s');
							$nextMorning->addDays(1);

							if ($showStartTime) {
								if ($requestDateStart->getDatabaseDate() == $nightStart->getDatabaseDate()) {
									$requestPeriodNight = "<span>L {$requestDateStart->getTimeOnly()}</span>";
								}
								else {
									$requestPeriodNight = "<span>L</span>";
								}

								$showStartTime = false;
							}
							else if (!$showStartTime &&  !$request->getOccupancy($nightEnd, $nextMorning)) {
								$requestPeriodNight = "<span>L {$requestDateFinish->getTimeOnly()}</span>";
							}
							else {
								$requestPeriodNight = "<span>L</span>";
							}
						}

						/* Display icon and time where appropriate
						 */
						$this->getPage()->setVar(array(
							'REQUEST_PERIOD_MORNING' => $requestPeriodMorning,
							'REQUEST_PERIOD_DAY' => $requestPeriodDay,
							'REQUEST_PERIOD_NIGHT' => $requestPeriodNight
						));

						$this->getPage()->parse('request_period_block', 'request_period', true);

						/* Iteration reset
						 */
						$morningStart->addDays(1);
						$dayStart->addDays(1);
						$nightStart->addDays(1);
						$nightEnd->addDays(1);

						unset($requestPeriodMorning, $requestPeriodDay, $requestPeriodNight);
					}

					if ($request->getStatus() != Leave_Request::STATUS_PENDING) {
						$this->getPage()->setVar(array(
							'REQUEST_STATUS' => $request->getStatus(true),
							'REQUEST_AUTHORISATION_DATETIME' => $request->getAuthorisationDatetime(true)->getAustralian(),
							'REQUEST_AUTHORISATION_USER_ID' => $request->getAuthorisationUser()->getId(),
							'REQUEST_AUTHORISATION_USER_NAME' => $request->getAuthorisationUser()->getName(),
						));
					}
					else {
						$this->getPage()->setVar(array(
							'REQUEST_STATUS' => $request->getStatus(true),
							'REQUEST_AUTHORISATION_DATETIME' => 'Pending Approval',
							'REQUEST_AUTHORISATION_USER_ID' => '',
							'REQUEST_AUTHORISATION_USER_NAME' => '',
							'CLASS' => $this->getPage()->getVar('CLASS') . ' leave-pending'
						));
					}

					$this->getPage()->setVar('empty_request_block', '');
					$this->getPage()->parse('request_block', 'request');

					/* Clear children after parsing out the parent
					 */
					$this->getPage()->setVar('request_period_block', '');
				}
				else {
					$this->getPage()->setVar('request_block', '');
					$this->getPage()->parse('empty_request_block', 'empty_request');
				}

				$this->getPage()->parse('row_block', 'row', true);
			}
		}
		else {
			$this->getPage()->parse('no_requests_block', 'no_requests');
			$this->getPage()->setVar('row_block', '');
		}

		$this->getPage()->setVar(array(
            'HOUSE_NAME' => $currentHouse->getHouse(),
            'HOUSE_ID' => $currentHouse->getId(),
            'REPORT_DATE' => $data['date'],
		));
    }

	/**
	 * Export CSV of renderHouseReport()
	 *
	 * @param $data
	 */
    public function exportHouseReport ($data) {
        $requestList = $data['requestList'];

        $this->getPage()->setAjax(true);

        header("Content-type: text/csv");
        header("Cache-Control: no-store, no-cache");
        header('Content-Disposition: attachment; filename="House Report.csv"');

        $stream = fopen("php://output",'w');

		if (count($requestList)) {
			foreach ($requestList as $request) {
				$fieldList = array(
					$request->getUser()->getName(),
					$request->getUser()->getYear()
				);

				if ($request->getId()) {

					/* Start and end of leave
					 */
					$requestDateStart = new Alaress_Date_Format($request['start'], 'y-m-d');
					$requestDateFinish = new Alaress_Date_Format($request['finish'], 'y-m-d');

					/* For each day that the person is on leave within the prescribed period of time, show an
					 * icon. If they are starting or ending their leave on this day, show the time as well
					 *
					 * Render 3 columns each day for 3 days
					 *
					 * Morning: 00:00:00 to 11:59:59
					 * Day: 12:00:00 to 17:59:59
					 * Night: 18:00:00 to 23:59:59
					 */
					$morningStart = $data['reportStart'];
					$dayStart = new Alaress_Date_Format($morningStart->getUnixTimestamp() + 12*60*60-1, 'U');
					$nightStart = new Alaress_Date_Format($dayStart->getUnixTimestamp() + 5*60*60, 'U');
					$nightEnd = new Alaress_Date_Format($nightStart->getUnixTimestamp() + 7*60*60, 'U');

					$showStartTime = true;

					/* First two columns in headings
					 */
					$headers = array('Name', 'Year');

					for ($i = 0; $i < self::RANGE; $i++) {

						/* Parse out the headings
						 */
						$headers[] = $morningStart->getDateOnly();
						$headers[] = $dayStart->getDateOnly();
						$headers[] = $nightStart->getDateOnly();

						/* If this person is on leave during the morning
						 */
						if ($request->getOccupancy($morningStart, $dayStart)) {

							/* Check whether next segment is occupied; if not, also show the time
							 */
							if ($showStartTime) {

								/* Specifically check for this day, to account for multi-week leave
								 */
								if ($requestDateStart->getDatabaseDate() == $morningStart->getDatabaseDate()) {
									$requestPeriodMorning = "L {$requestDateStart->getTimeOnly()}";
								}
								else {
									$requestPeriodMorning = "L";
								}

								$showStartTime = false;
							}
							else if (!$showStartTime && !$request->getOccupancy($dayStart, $nightStart)) {
								$requestPeriodMorning = "L {$requestDateFinish->getTimeOnly()}";
							}
							else {
								$requestPeriodMorning = "L";
							}
						}

						/* If this person is on leave during the day
						 */
						if ($request->getOccupancy($dayStart, $nightStart)) {
							if ($showStartTime) {

								/* Specifically check for this day, to account for multi-week leave
								 */
								if ($requestDateStart->getDatabaseDate() == $dayStart->getDatabaseDate()) {
									$requestPeriodDay = "L {$requestDateStart->getTimeOnly()}";
								}
								else {
									$requestPeriodDay = "L";
								}

								$showStartTime = false;
							}
							else if (!$showStartTime && !$request->getOccupancy($nightStart, $nightEnd)) {
								$requestPeriodDay = "L {$requestDateFinish->getTimeOnly()}";
							}
							else {
								$requestPeriodDay = "L";
							}
						}

						/* If this person is on leave during the night
						 */
						if ($request->getOccupancy($nightStart, $nightEnd)) {

							/* Check whether the next morning is occupied
							 */
							$nextMorning = new Alaress_Date_Format($dayStart->getDatabase(), 'y-m-d h:i:s');
							$nextMorning->addDays(1);

							if ($showStartTime) {

								/* Specifically check for this day, to account for multi-week leave
								 */
								if ($requestDateStart->getDatabaseDate() == $nightStart->getDatabaseDate()) {
									$requestPeriodNight = "L {$requestDateStart->getTimeOnly()}";
								}
								else {
									$requestPeriodNight = "L";
								}

								$showStartTime = false;
							}
							else if (!$showStartTime &&  !$request->getOccupancy($nightEnd, $nextMorning)) {
								$requestPeriodNight = "L {$requestDateFinish->getTimeOnly()}";
							}
							else {
								$requestPeriodNight = "L";
							}
						}

						/* Display icon and time where appropriate
						 */
						$fieldList[] = $requestPeriodMorning;
						$fieldList[] = $requestPeriodDay;
						$fieldList[] = $requestPeriodNight;

						/* Iteration reset
						 */
						$morningStart->addDays(1);
						$dayStart->addDays(1);
						$nightStart->addDays(1);
						$nightEnd->addDays(1);

						unset($requestPeriodMorning, $requestPeriodDay, $requestPeriodNight);
					}

					/* Authorisation fields
					 */
					if ($request->getStatus() != Leave_Request::STATUS_PENDING) {
						$fieldList[] = $request->getAuthorisationDatetime(true)->getAustralian();
						$fieldList[] = $request->getAuthorisationUser()->getName();
					}
                    else {
						$fieldList[] = 'Pending Approval';
                        $fieldList[] = ' ';
                    }
                }

				fputcsv($stream, $headers);
                fputcsv($stream, $fieldList);
            }
        }

        fclose($stream);
    }

	public function executeCateringReport($date) {
		$data = array();

		$data['date'] = $date;

		$data['houseList'] = $this->getRequestMapper($date)->getCateringReport($date);

		if (count($data['houseList'])) {
			foreach ($data['houseList'] as $house) {
				$data['absentMorningTotal'] += $house['absent_morning'];
				$data['absentDayTotal'] += $house['absent_day'];
				$data['absentNightTotal'] += $house['absent_night'];
				$data['presentMorningTotal'] += $house['present_morning'];
				$data['presentDayTotal'] += $house['present_day'];
				$data['presentNightTotal'] += $house['present_night'];
			}
		}

		$data['house'] = $this->getHouseMapper()->findById($_SESSION['houseId']);

		return $data;
    }

	/**
	 * Render catering data. Null values can exist for absent_morning etc. when a House
	 * as no leave requests for it. Casting it as an integer forces it to zero.
	 *
	 * @param $data
	 */
	public function renderCateringReport($data) {
		$this->getPage()->setFile(TEMPLATE_content, 'modules/leave/cateringReport.html');
		$this->getPage()->setBlock(TEMPLATE_content, 'no_houses', 'no_houses_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'house', 'house_block');

		$currentHouse = $data['house'];

		$houseList = $data['houseList'];

		if (count($houseList)) {
			$this->getPage()->setVar('no_houses_block', '');

			foreach ($houseList as $i => $house) {
				$this->getPage()->setVar(array(
					'REPORT_DATE' => $data['date'],
					'HOUSE_ID' => $house['houseId'],
					'HOUSE_NAME' => $house['name'],
					'ABSENT_MORNING' => (int)$house['absent_morning'],
					'ABSENT_DAY' => (int)$house['absent_day'],
					'ABSENT_NIGHT' => (int)$house['absent_night'],
					'PRESENT_MORNING' => (int)$house['present_morning'],
					'PRESENT_DAY' => (int)$house['present_day'],
					'PRESENT_NIGHT' => (int)$house['present_night'],
					'CLASS' => $i % 2 ? 'dark' : ''
				));

				$this->getPage()->parse('house_block', 'house', true);
			}
		}
		else {
			$this->getPage()->parse('no_houses_block', 'no_houses');
		}

		$this->getPage()->setVar(array(
			'HOUSE_ID' => $currentHouse->getId(),
			'HOUSE_NAME' => $currentHouse->getHouse(),
			'ABSENT_MORNING_TOTAL' => $data['absentMorningTotal'],
			'ABSENT_DAY_TOTAL' => $data['absentDayTotal'],
			'ABSENT_NIGHT_TOTAL' => $data['absentNightTotal'],
			'PRESENT_MORNING_TOTAL' => $data['presentMorningTotal'],
			'PRESENT_DAY_TOTAL' => $data['presentDayTotal'],
			'PRESENT_NIGHT_TOTAL' => $data['presentNightTotal'],
		));
	}

	public function exportCateringReport ($data) {
        $houseList = $data['houseList'];

        $this->getPage()->setAjax(true);

        header("Content-type: text/csv");
        header("Cache-Control: no-store, no-cache");
        header('Content-Disposition: attachment; filename="Catering Report.csv"');

        $stream = fopen("php://output",'w');

        $headerList = array('House', 'Breakfast Absent', 'Lunch Absent', 'Dinner Absent', 'Breakfast Present', 'Lunch Present', 'Dinner Present');

        fputcsv($stream, $headerList);

        if ( count($houseList) ) {
            foreach ( $houseList as $house ) {
                $fieldList = array($house['house'], $house['absent_morning'], $house['absent_day'], $house['absent_night'], $house['present_morning'], $house['present_day'], $house['present_night']);

                fputcsv($stream, $fieldList);
            }
        }

        fclose($stream);
    }

    public function sendUserNotice ($request) {
        $userNoticeEmail = new Mailer('../modules/leave/userNoticeEmail.html', 'Leave Request Status Update', $request->getUser()->getAltEmail());
        $userNoticeEmail->setVar( array(
            'LEAVE_USER_NAME' => $request->getUser()->getName(),
            'LEAVE_DATE' => $request->getStart(true)->getLong(),
            'LEAVE_DURATION' => $request->getDuration(),
            'LEAVE_STATUS' => $request->getStatus(true),
            'LEAVE_AUTHORISATION_NAME' => $request->getAuthorisationUser()->getName()
        ) );

        $userNoticeEmail->send();
    }

    public function sendParentNotice ($request) {
        $parentList = $this->getExternalUser()->getStudentContactList( $request->getUserId() );

        if ( is_array($parentList) ) {
            foreach ( $parentList as $parent ) {
                $parentNoticeEmail = new Mailer('../modules/leave/parentNoticeEmail.html', 'Leave Request Status Update', $parent['StudentContactDefaultEmail']);
                $parentNoticeEmail->setVar( array(
                    'PARENT_NAME' => "{$parent['StudentContactPreferred']} {$parent['StudentContactSurname']}",
                    'LEAVE_START' => $request->getStart(true)->getLong(),
                    'LEAVE_FINISH' => $request->getFinish(true)->getLong(),
                    'LEAVE_HOST_NAME' => $request->getHostName(),
                    'LEAVE_HOST_PHONE' => $request->getHostPhone(),
                    'LEAVE_USER_NAME' => $request->getUser()->getName(),
                    'LEAVE_USER_PHONE' => $request->getUserPhone(),
                    'LEAVE_DESCRIPTION' => $request->getDescription()
                ) );

                $parentNoticeEmail->send();
            }
        }
    }

	/**
	 * Set external user
	 *
	 * @param $externalUser
	 */
    public function setExternalUser($externalUser) {
        $this->externalUser = $externalUser;
    }

	/**
	 * Get external user
	 *
	 * @return mixed
	 */
    public function getExternalUser() {
        return $this->externalUser;
    }

	/**
	 * Set group handler
	 *
	 * @param $group
	 */
	private function setGroup($group) {
		$this->group = $group;
	}

	/**
	 * Get group handler
	 *
	 * @return mixed
	 */
	private function getGroup() {
		return $this->group;
	}

	/**
	 * Setter for $requestMapper
	 *
	 * @param Leave_RequestMapper $requestMapper
	 *
	 * @return void
	 */
	protected function setRequestMapper($requestMapper) {
		$this->requestMapper = $requestMapper;
	}

	/**
	 * Getter for $requestMapper
	 *
	 * @return Leave_RequestMapper
	 */
	protected function getRequestMapper() {
		return $this->requestMapper;
	}

	/**
	 * Setter for $userMapper
	 *
	 * @param NewUserMapper $userMapper
	 *
	 * @return void
	 */
	public function setUserMapper($userMapper) {
		$this->userMapper = $userMapper;
	}

	/**
	 * Getter for $userMapper
	 *
	 * @return NewUserMapper
	 */
	public function getUserMapper() {
		return $this->userMapper;
	}

	/**
	 * Setter for $houseMapper
	 *
	 * @param HouseMapper $houseMapper
	 *
	 * @return void
	 */
	public function setHouseMapper($houseMapper) {
		$this->houseMapper = $houseMapper;
	}

	/**
	 * Getter for $houseMapper
	 *
	 * @return HouseMapper
	 */
	public function getHouseMapper() {
		return $this->houseMapper;
	}
}