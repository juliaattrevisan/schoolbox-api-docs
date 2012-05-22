<?php
/**
 * Leave Controller
 *
 * @author      Alex Tworowsky <aetworowsky@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Leave_Controller extends Controller {

	/* Unused constants which document when morning, day and night occur
	 * for Leave Reports and Catering Reports respectively
	 */
	const LEAVE_THRESHOLD_MORNING = '9:59:59';
	const LEAVE_THRESHOLD_DAY = '18:59:59';
	const LEAVE_THRESHOLD_NIGHT = '21:59:59';

	const CATERING_THRESHOLD_MORNING = '11:59:59';
	const CATERING_THRESHOLD_DAY = '16:59:59';
	const CATERING_THRESHOLD_NIGHT = '23:59:59';

	/* Consts for days
	 */
	const FRIDAY = 5;
	const SATURDAY = 6;
	const SUNDAY = 7;

	/* Show leave requests over RANGE days
	 * Change
	 */
	const RANGE = 3;

	/* Time map array
	 * A map of actual time values => presentation information
	 * used in the Leave Time/Return Time dropdowns
	 */
	private $timeMap = array(
		'00:30:00' => '12:30 AM',
		'01:00:00' => '1:00 AM',
		'01:30:00' => '1:30 AM',
		'02:00:00' => '2:00 AM',
		'02:30:00' => '2:30 AM',
		'03:00:00' => '3:00 AM',
		'03:30:00' => '3:30 AM',
		'04:00:00' => '4:00 AM',
		'04:30:00' => '4:30 AM',
		'05:00:00' => '5:00 AM',
		'05:30:00' => '5:30 AM',
		'06:00:00' => '6:00 AM',
		'06:30:00' => '6:30 AM',
		'07:00:00' => '7:00 AM',
		'07:30:00' => '7:30 AM',
		'08:00:00' => '8:00 AM',
		'08:30:00' => '8:30 AM',
		'09:00:00' => '9:00 AM',
		'09:30:00' => '9:30 AM',
		'10:00:00' => '10:00 AM',
		'10:30:00' => '10:30 AM',
		'11:00:00' => '11:00 AM',
		'11:30:00' => '11:30 AM',
		'12:00:00' => '12:00 PM',
		'12:30:00' => '12:30 PM',
		'13:00:00' => '1:00 PM',
		'13:30:00' => '1:30 PM',
		'14:00:00' => '2:00 PM',
		'14:30:00' => '2:30 PM',
		'15:00:00' => '3:00 PM',
		'15:30:00' => '3:30 PM',
		'16:00:00' => '4:00 PM',
		'16:30:00' => '4:30 PM',
		'17:00:00' => '5:00 PM',
		'17:30:00' => '5:30 PM',
		'18:00:00' => '6:00 PM',
		'18:30:00' => '6:30 PM',
		'19:00:00' => '7:00 PM',
		'19:30:00' => '7:30 PM',
		'20:00:00' => '8:00 PM',
		'20:30:00' => '8:30 PM',
		'21:00:00' => '9:00 PM',
		'21:30:00' => '9:30 PM',
		'22:00:00' => '10:00 PM',
		'22:30:00' => '10:30 PM',
		'23:00:00' => '11:00 PM',
		'23:30:00' => '11:30 PM',
	);

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
        $this->setHouseMapper(HouseResidentialMapper::getInstance());
        $this->setExternalUser(new External_User());
		$this->setGroup(new Group(HOUSEMASTER_SERVICE));
    }

	/**
	 * Test email deployments
	 */
	public function executeEmailTest() {
		$email = new Mailer('../modules/leave/userNoticeEmail.html', 'test' , 'steve.chan@alaress.com.au');

		$email->setFrom(Cfg::get('mail_from_address'), 'Test Schoolbox');
		$email->send();
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
	 * Render host type dropdown
	 *
	 * @param $request
	 */
	private function renderHostTypeDropdown($request) {
		$this->getPage()->setBlock(TEMPLATE_content, 'host_type', 'host_type_block');

		/* Iterate through each option and preselect
		 */
		foreach($request->getHostTypeMap() as $value => $label) {
			if ($request->getHostType() == $value) {
				$selected = 'selected=\'selected\'';
			}
			else {
				$selected = '';
			}

			$this->getPage()->setVar(array(
				'HOST_TYPE_VALUE' => $value,
				'HOST_TYPE_LABEL' => $label,
				'HOST_TYPE_SELECTED' => $selected,
			));

			$this->getPage()->parse('host_type_block', 'host_type', true);
		}
	}

	/**
	 * Render the dropdown for the start of Leave
	 *
	 * @param null $startTime
	 */
	private function renderStartTimeDropdown($startTime = null) {
		$this->getPage()->setBlock(TEMPLATE_content, 'request_start_time', 'request_start_time_block');

		/* Iterate through each option and preselect
		 */
		foreach($this->getTimeMap() as $value => $label) {
			if ($startTime == $value) {
				$selected = 'selected=\'selected\'';
			}
			else {
				$selected = '';
			}

			$this->getPage()->setVar(array(
				'REQUEST_START_TIME_VALUE' => $value,
				'REQUEST_START_TIME_LABEL' => $label,
				'REQUEST_START_TIME_SELECTED' => $selected,
			));

			$this->getPage()->parse('request_start_time_block', 'request_start_time', true);
		}
	}

	/**
	 * Render the dropdown for the end of Leave
	 *
	 * @param null $finishTime
	 */
	private function renderFinishTimeDropdown($finishTime = null) {
		$this->getPage()->setBlock(TEMPLATE_content, 'request_finish_time', 'request_finish_time_block');

		/* Iterate through each option and preselect
		 */
		foreach($this->getTimeMap() as $value => $label) {
			if ($finishTime == $value) {
				$selected = 'selected=\'selected\'';
			}
			else {
				$selected = '';
			}

			$this->getPage()->setVar(array(
				'REQUEST_FINISH_TIME_VALUE' => $value,
				'REQUEST_FINISH_TIME_LABEL' => $label,
				'REQUEST_FINISH_TIME_SELECTED' => $selected,
			));


			$this->getPage()->parse('request_finish_time_block', 'request_finish_time', true);
		}
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

		/* Render dropdowns
		 */
		$request = new Leave_Request();

		$this->renderStartTimeDropdown();
		$this->renderFinishTimeDropdown();
		$this->renderHostTypeDropdown($request);

        $this->getPage()->setVar($data);

		/* Add terms and conditions
		 */
		$this->getPage()->setVar(array(
			'TERMS_AND_CONDITIONS' => Cfg::get('terms_and_conditions')
		));

		/* Apply autocomplete if appropriate - staff can submit on someone else's behalf
		 */
		if ($this->getGroup()->getPermission(null)) {
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

		$start = new Alaress_Date_Format("{$data['request']['start']['date']} {$data['request']['start']['time']}", 'd/m/y h:i:s');
		$finish = new Alaress_Date_Format("{$data['request']['finish']['date']} {$data['request']['finish']['time']}", 'd/m/y h:i:s');
		$created = new Alaress_Date_Format(time(), 'U');
		$request = new Leave_Request();

		/* Use the userId field when submitting on someone else's behalf,
		 * otherwise just your own id
		 */
		if ($this->getGroup()->getPermission(null)) {
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

		$this->sendUserNotice($request);
		$this->sendParentNotice($request);

		SitePage::localRedir("/modules/leave/student/{$request->getUserId()}");
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
					'USER_BOARDING_STATUS' => $request->getUser()->getAbbreviatedBoardingStatus(),
					'REQUEST_ID' => $request->getId(),
					'REQUEST_START' => $request->getStart()->getAustralian(),
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
		$house = $this->getHouseMapper()->findById($user->getResidentialHouseId());
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

		/* Only allow students to see their own leave requests
		 * Allow staff/superusers to see anyone's
		 */
		if ($this->getGroup()->getPermission(null) || $_SESSION['id'] == $data['user']->getId()) {
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

			/* Render each request
			 */
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
						'REQUEST_START' => $request->getStart()->getAustralian(),
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
		else {
			error(19);
		}
	}

    /**
     * Processes modifications against a list of requests
     *
     * @param Array $data
     * @return void
     */
	public function processModifyRequestList($data) {

		/* Only allow Teachers/Staff to approve/reject requests
		 */
		if ($this->getGroup()->getPermission(null)) {
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
						$this->sendParentNotice($request);
					}
				}
			}

			$this->getPage()->localRedir("/modules/leave/student/{$user['id']}");
		}
		else {
			error(19);
		}
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
	 * Render confirmed by checkbox array
	 * Knows whether to show them depending on user type
	 *
	 * @param $request
	 */
	private function renderConfirmedByCheckboxes($request) {
		$this->getPage()->setBlock(TEMPLATE_content, 'confirmed_by', 'confirmed_by_block');

		/* Iterate through options
		 */
		if ($this->getGroup()->getPermission(null)) {
			foreach($request->getConfirmedByMap() as $value => $label) {
				if ($request->getConfirmedBy() == $value) {
					$selected = 'checked=\'checked\'';
				}
				else {
					$selected = '';
				}

				$this->getPage()->setVar(array(
					'CONFIRMED_BY_VALUE' => $value,
					'CONFIRMED_BY_LABEL' => $label,
					'CONFIRMED_BY_SELECTED' => $selected,
				));

				$this->getPage()->parse('confirmed_by_block', 'confirmed_by', true);
			}
		}
		else {
			$this->getPage()->setVar('has_confirmed_by_block', '');
		}
	}

    /**
     * Renders a modify request form
     *
     * @param Array $data
     * @return void
     */
	public function renderModifyRequest($data) {

		/* Only allow Teachers/Staff to modify requests
		 */
		if ($this->getGroup()->getPermission(null)) {
			$request = $data['request'];
			$user = $request->getUser();
			$house = $this->getHouseMapper()->findById($user->getHouse());

			$this->getPage()->setFile(TEMPLATE_content, 'modules/leave/modifyRequest.html');
			$this->getPage()->setBlock(TEMPLATE_content, 'next_pending', 'next_pending_block');
			$this->getPage()->setBlock(TEMPLATE_content, 'prev_pending', 'prev_pending_block');

			/* Render dropdowns
			 */
			$this->renderStartTimeDropdown($request->getStart()->getDatabaseTime());
			$this->renderFinishTimeDropdown($request->getFinish()->getDatabaseTime());
			$this->renderHostTypeDropdown($request);
			$this->renderConfirmedByCheckboxes($request);

			$this->getPage()->setVar(array(
				'REQUEST_ID' => $request->getId(),
				'REQUEST_STATUS' => $request->getStatus(true),
				'USER_NAME' => $user->getName(),
				'USER_EXTERNAL_ID' => $user->getSynergyId(),
				'USER_YEAR' => $user->getYear(),
				'USER_HOUSE' => $house->getHouse(),
				'REQUEST_START_DATE' => $request->getStart()->getLocalShort(),
				'REQUEST_FINISH_DATE' => $request->getFinish()->getLocalShort(),
				'REQUEST_HOST_NAME' => $request->getHostName(),
				'REQUEST_HOST_PHONE' => $request->getHostPhone(),
				'REQUEST_USER_PHONE' => $request->getUserPhone(),
				'REQUEST_DESCRIPTION' => $request->getDescription(),
				'REQUEST_NOTES' => $request->getNotes(),
			));

			/* What is the next/previous pending request?
			 */
			$nextRequest = $this->getRequestMapper()->findNextPendingByHouseId($request['id'], $user->getResidentialHouseId());

			if ($nextRequest) {
				$this->getPage()->setVar(array(
					'NEXT_PENDING_ID' => $nextRequest['id']
				));

				$this->getPage()->parse('next_pending_block', 'next_pending');
			}
			else {
				$this->getPage()->setVar('next_pending_block', '');
			}

			$previousRequest = $this->getRequestMapper()->findPreviousPendingByHouseId($request['id'], $user->getResidentialHouseId());

			if ($previousRequest) {
				$this->getPage()->setVar(array(
					'PREV_PENDING_ID' => $previousRequest['id']
				));

				$this->getPage()->parse('prev_pending_block', 'prev_pending');
			}
			else {
				$this->getPage()->setVar('prev_pending_block', '');
			}
		}
		else {
			error(19);
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

		$start = new Alaress_Date_Format("{$data['request']['start']['date']} {$data['request']['start']['time']}", 'd/m/y h:i:s');
		$finish = new Alaress_Date_Format("{$data['request']['finish']['date']} {$data['request']['finish']['time']}", 'd/m/y h:i:s');

		$request->setStart($start->getDatabase());
		$request->setFinish($finish->getDatabase());
		$request->setHostType($data['request']['hostType']);
		$request->setHostName($data['request']['hostName']);
		$request->setHostPhone($data['request']['hostPhone']);
		$request->setUserPhone($data['request']['userPhone']);
		$request->setDescription($data['request']['description']);
		$request->setNotes($data['request']['notes']);
		$request->setConfirmedBy($data['request']['confirmedBy']);

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
			$this->sendParentNotice($request);
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
		 */
		$reportStart = new Alaress_Date_Format($date, 'd/m/y');
		$reportStart->addDays(self::FRIDAY - $reportStart->getWeekDayNumber());

		$reportFinish = new Alaress_Date_Format($date, 'd/m/y');
		$reportFinish->addDays(self::SUNDAY - $reportFinish->getWeekDayNumber());
		$reportFinish->addHours(24);

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

		$currentTime = new Alaress_Date_Format(time(), 'U');

		/* Render output
		 */
        $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/houseReport.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'house', 'house_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'no_requests', 'no_requests_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'row', 'row_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'report_header', 'report_header_block');

		$this->getPage()->setBlock(TEMPLATE_content, 'report_header_subtitle', 'report_header_subtitle_block');
		$this->getPage()->setBlock('report_header_subtitle', 'report_header_subtitle_show_am', 'report_header_subtitle_show_am_block');
		$this->getPage()->setBlock('report_header_subtitle', 'report_header_subtitle_show_day', 'report_header_subtitle_show_day_block');
		$this->getPage()->setBlock('report_header_subtitle', 'report_header_subtitle_show_pm', 'report_header_subtitle_show_pm_block');
		$this->getPage()->setBlock('report_header_subtitle', 'report_header_subtitle_hide_am', 'report_header_subtitle_hide_am_block');
		$this->getPage()->setBlock('report_header_subtitle', 'report_header_subtitle_hide_day', 'report_header_subtitle_hide_day_block');

        $this->getPage()->setBlock('row', 'request', 'request_block');
		$this->getPage()->setBlock('row', 'empty_request', 'empty_request_block');
		$this->getPage()->setBlock('request', 'request_period', 'request_period_block');
		$this->getPage()->setBlock('request', 'request_authorisation', 'request_authorisation_block');

		$this->getPage()->setBlock('request_period', 'request_period_show_am', 'request_period_show_am_block');
		$this->getPage()->setBlock('request_period', 'request_period_show_day', 'request_period_show_day_block');
		$this->getPage()->setBlock('request_period', 'request_period_show_pm', 'request_period_show_pm_block');
		$this->getPage()->setBlock('request_period', 'request_period_hide_am', 'request_period_hide_am_block');
		$this->getPage()->setBlock('request_period', 'request_period_hide_day', 'request_period_hide_day_block');

		$this->getPage()->setVar(array(
			'GENERATED_AT' => $currentTime->getShort()
		));

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
					'REPORT_HEADER_DATE' => $headerDate->getLongDateOnly(),
				));

				/* Hide the first two columns of the first group
				 */
				if (!$i) {
					$this->getPage()->setVar('report_header_subtitle_show_am_block', '');
					$this->getPage()->setVar('report_header_subtitle_show_day_block', '');
					$this->getPage()->parse('report_header_subtitle_hide_am_block', 'report_header_subtitle_hide_am');
					$this->getPage()->parse('report_header_subtitle_hide_day_block', 'report_header_subtitle_hide_day');

				}
				else {
					$this->getPage()->parse('report_header_subtitle_show_am_block', 'report_header_subtitle_show_am');
					$this->getPage()->parse('report_header_subtitle_show_day_block', 'report_header_subtitle_show_day');
					$this->getPage()->setVar('report_header_subtitle_hide_am_block', '');
					$this->getPage()->setVar('report_header_subtitle_hide_day_block', '');
				}

				$this->getPage()->parse('report_header_subtitle_show_pm_block', 'report_header_subtitle_show_pm');

				$this->getPage()->parse('report_header_block', 'report_header', true);
				$this->getPage()->parse('report_header_subtitle_block', 'report_header_subtitle', true);

				/* Clear children after parsing out the parent
				 */
				$this->getPage()->setVar('report_header_subtitle_show_am_block', '');
				$this->getPage()->setVar('report_header_subtitle_show_day_block', '');
				$this->getPage()->setVar('report_header_subtitle_show_pm_block', '');
				$this->getPage()->setVar('report_header_subtitle_hide_am_block', '');
				$this->getPage()->setVar('report_header_subtitle_hide_day_block', '');
			}

			/* Generate, preprocess and render a House Report
			 */
			$houseReport = new Leave_Report_Kings_House($this->getPage(), $data, $requestList, self::RANGE);
			$houseReport->recombination();
			$houseReport->render();
		}
		else {
			$this->getPage()->setVar('report_header_block', '');
			$this->getPage()->setVar('report_header_subtitle_block', '');

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
					$subheaders = array('', '');

					for ($i = 0; $i < self::RANGE; $i++) {

						/* Parse out the headings
						 */
						$headers[] = $morningStart->getDateOnly();
						$headers[] = $dayStart->getDateOnly();
						$headers[] = $nightStart->getDateOnly();

						$subheaders[] = 'Morning';
						$subheaders[] = 'Day';
						$subheaders[] = 'Night';

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

				/* Additional headers for authorisation
				 */
				$headers[] = 'Authorisation Status';

				fputcsv($stream, $headers);
				fputcsv($stream, $subheaders);
                fputcsv($stream, $fieldList);
            }
        }

        fclose($stream);
    }

	/**
	 * Get catering requirements for the Friday, Saturday and Sunday.
	 * This is the period of time when people can go on leave.
	 *
	 * @param $date
	 * @return array
	 */
	public function executeCateringReport($date) {

		/* Collate results for each day
		 */
		$data = array();
		$data['date'] = $date;

		/* Skip Friday
		 */
		for ($i = self::SATURDAY; $i <= self::SUNDAY; $i++) {
			$currentDate = new Alaress_Date_Format($date, 'd/m/y');
			$currentDate->addDays($i - $currentDate->getWeekDayNumber());

			$data['dateList'][] = $currentDate;

			$allHouses = $this->getRequestMapper($date)->getCateringReport($currentDate);
			$data['houseList'][] = $allHouses;

			/* Summate totals for each house in this segment
			 */
			$totals = array();

			foreach($allHouses as $currentHouse) {
				$totals['present_morning'] += $currentHouse['present_morning'];
				$totals['present_day'] += $currentHouse['present_day'];
				$totals['present_night'] += $currentHouse['present_night'];
			}

			/* Remember totals for this segment
			 */
			$data['totals'][] = $totals;
		}

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
		$this->getPage()->setBlock(TEMPLATE_content, 'request_date_header', 'request_date_header_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'request_period_total', 'request_period_total_block');
		$this->getPage()->setBlock('house', 'request_period', 'request_period_block');

		$this->getPage()->setVar(array(
			'REPORT_DATE' => $data['date']
		));

		/* For each day, render a heading
		 * Exclude initial Friday -_-
		 */
		for ($i = 0; $i < count($data['dateList']); $i++) {
			$currentDate = $data['dateList'][$i];

			$this->getPage()->setVar(array(
				'REQUEST_DATE_HEADER_DATE' => $currentDate->getLongDateOnly()
			));

			$this->getPage()->parse('request_date_header_block', 'request_date_header', true);
		}

		/* For each day, render totals
		 */
		for ($i = 0; $i < count($data['totals']); $i++) {
			$currentSegment = $data['totals'][$i];

			$this->getPage()->setVar(array(
				'REQUEST_PERIOD_TOTAL_MORNING' => $currentSegment['present_morning'],
				'REQUEST_PERIOD_TOTAL_DAY' => $currentSegment['present_day'],
				'REQUEST_PERIOD_TOTAL_NIGHT' => $currentSegment['present_night'],
			));

			$this->getPage()->parse('request_period_total_block', 'request_period_total', true);
		}

		/* For each house
		 */
		if (is_array($data['houseList'][0]) && count($data['houseList'][0])) {
			for ($i = 0; $i < count($data['houseList'][0]); $i++) {

				/* For each day
				 * (Exclude Friday -_-)
				 */
				for ($j = 0; $j < count($data['dateList']); $j++) {
					$this->getPage()->setVar(array(
						'REQUEST_PERIOD_MORNING' => (int)$data['houseList'][$j][$i]['present_morning'],
						'REQUEST_PERIOD_DAY' => (int)$data['houseList'][$j][$i]['present_day'],
						'REQUEST_PERIOD_NIGHT' => (int)$data['houseList'][$j][$i]['present_night'],
					));

					$this->getPage()->parse('request_period_block', 'request_period', true);
				}

				/* Render house row, with alternating background colours
				 */
				$this->getPage()->setVar(array(
					'HOUSE_ID' => $data['houseList'][0][$i]['houseId'],
					'HOUSE_NAME' => $data['houseList'][0][$i]['name'],
					'CLASS' => $i % 2 ? 'dark' : '',
				));

				$this->getPage()->parse('house_block', 'house', true);

				/* Clear children after parsing out the parent
				 */
				$this->getPage()->setVar('request_period_block', '');
			}

			$this->getPage()->setVar('no_houses_block', '');
		}
		else {
			$this->getPage()->parse('no_houses_block', 'no_houses');
		}
	}

	/**
	 * Export a csv version of the catering report. Algorithm is similar to
	 * renderCateringReport, but due to non-templating things have to be
	 * fputcsv'd in order
	 *
	 * @param $data
	 */
	public function exportCateringReport($data) {
        $this->getPage()->setAjax(true);

        header("Content-type: text/csv");
        header("Cache-Control: no-store, no-cache");
        header('Content-Disposition: attachment; filename="Catering Report.csv"');

        $stream = fopen("php://output",'w');

		/* For each day, render a heading
		 */
		$headers = array();
		$headers[] = 'House';

		$subheaders = array();
		$subheaders[] = '';

		foreach($data['dateList'] as $currentDate) {
			$headers[] = $currentDate->getLocalShort();
			$headers[] = $currentDate->getLocalShort();
			$headers[] = $currentDate->getLocalShort();

			$subheaders[] = 'Breakfast';
			$subheaders[] = 'Lunch';
			$subheaders[] = 'Dinner';
		}

		fputcsv($stream, $headers);
		fputcsv($stream, $subheaders);

		/* For each house
		 */
		if (is_array($data['houseList'][0]) && count($data['houseList'][0])) {
			for ($i = 0; $i < count($data['houseList'][0]); $i++) {

				$row = array();
				$row[] = $data['houseList'][0][$i]['name'];

				/* For each day
				 */
				for ($j = 0; $j < count($data['dateList']); $j++) {
					$row[] = (int)$data['houseList'][$j][$i]['present_morning'];
					$row[] = (int)$data['houseList'][$j][$i]['present_day'];
					$row[] = (int)$data['houseList'][$j][$i]['present_night'];
				}

				fputcsv($stream, $row);
			}
		}

		/* For each day, render totals
		 */
		$totals = array();
		$totals[] = 'Total';

		foreach($data['totals'] as $currentSegment) {
			$totals[] = $currentSegment['present_morning'];
			$totals[] = $currentSegment['present_day'];
			$totals[] = $currentSegment['present_night'];
		}

		fputcsv($stream, $totals);

        fclose($stream);
    }

	/**
	 * Send email notification
	 *
	 * @param $request
	 */
    public function sendUserNotice($request) {

		/* Choose the Factory, have it produce() the notice object and send
		 */
		$factory = new Leave_Notice_Kings_User_Factory($request);
		$notice = $factory->produce();
		$notice->send();
    }

	/**
	 * Send a notice to a parent when someone creates a leave request
	 *
	 * At Kings Live, parent_notification started saving as integer 1/0, instead
	 * of the usual true/false string?
	 *
	 * @param $request
	 */
    public function sendParentNotice($request) {
		if ((Cfg::get('parent_notification') == 0) || (Cfg::get('parent_notification') == 'false')) {

			/* Choose the Factory, have it produce() the notice object and send
			 */
			$factory = new Leave_Notice_Kings_Parent_Factory($request);
			$notice = $factory->produce();
			$notice->send();
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

	/**
	 * Set time map
	 *
	 * @param $timeMap
	 */
	private function setTimeMap($timeMap) {
		$this->timeMap = $timeMap;
	}

	/**
	 * Get time map
	 *
	 * @return array
	 */
	private function getTimeMap() {
		return $this->timeMap;
	}
}