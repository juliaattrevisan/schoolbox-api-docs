<?php
/**
 * Leave Controller
 *
 * @author      Alex Tworowsky <aetworowsky@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Leave_Controller extends Controller
{
    /**
	 * Unused constants which document when morning, day and night occur
     * for Leave Reports and Catering Reports respectively
     */
    const LEAVE_THRESHOLD_MORNING = '9:59:59';
    const LEAVE_THRESHOLD_DAY = '18:59:59';
    const LEAVE_THRESHOLD_NIGHT = '21:59:59';

    const CATERING_THRESHOLD_MORNING = '11:59:59';
    const CATERING_THRESHOLD_DAY = '16:59:59';
    const CATERING_THRESHOLD_NIGHT = '23:59:59';

    /**
	 * Consts for days
     */
    const FRIDAY = 5;
    const SATURDAY = 6;
    const SUNDAY = 7;

    /**
	 * Show leave requests over RANGE days
     * Change
     */
    const RANGE = 3;

    protected $requestMapper;
    protected $userMapper;
    protected $houseMapper;
    protected $externalUser;
    protected $group;

    /**
     * Travel methods for a leave request.
     *
     * @var array
     */
    protected $travelTypes;

    /**
     * Host types for a leave request.
     *
     * @var \Schoolbox\Collection|\Leave\HostType[]
     */
    protected $hostTypes;

    /**
	 * Time map array
     * A map of actual time values => presentation information
     * used in the Leave Time/Return Time dropdowns
     */
    private $timeMap = array(
        '00:00:00' => '12:00 AM',
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

    /**
     * Constructor
     *
     * @param Page $page
     */
    public function __construct($page = null)
    {
        parent::__construct($page);

        $this->setUserMapper(\Schoolbox\UserMapper::getInstance());
        $this->setRequestMapper(Leave_RequestMapper::getInstance());
        $this->setHouseMapper(HouseResidentialMapper::getInstance());
        $this->setExternalUser(new External_User());
        $this->setGroup(new Group(HOUSEMASTER_SERVICE));
        // Create a phantom "Not Specified" travel type (it doesn't exist in the DB)
        $emptyTravelType = new Leave_Request_TravelType();
        $emptyTravelType->setName('Not specified');
        $travelMethods = array(0 => $emptyTravelType);

        $allTravelTypes = Leave_Request_TravelTypeMapper::getInstance()->findAll(array(
            'order' => array(
                'sequence' => 'asc'
            )
        ));

        foreach ($allTravelTypes as $method) {
            $travelMethods[$method->getId()] = $method;
        }

        $this->setTravelTypes($travelMethods);

        // Set host types too
        $this->setHostTypes(\Leave\HostTypeMapper::getInstance()->findAll());
    }

    /**
     * Test email deployments
     */
    public function executeEmailTest()
    {
        $email = new Mailer('../modules/leave/userNoticeEmail.html', 'test', 'steve.chan@alaress.com.au');

        $email->setFrom(Cfg::get('mail_from_address'), 'Test Schoolbox');
        $email->send();
    }

    /**
     * Render host type dropdown
     *
     * @param $request
     */
    private function renderHostTypeDropdown($request)
    {
        $this->getPage()->setBlock(TEMPLATE_content, 'host_type', 'host_type_block');

        /* Iterate through each option and preselect
		 */
        foreach ($this->getHostTypes() as $currentHostType) {


            if ($request->getHostType() instanceof \Leave\HostType && $request->getHostType()->getId() == $currentHostType->getId()) {
                $selected = 'selected=\'selected\'';
            } else {
                $selected = '';
            }

            $this->getPage()->setVar(
                array(
                    'HOST_TYPE_VALUE' => $currentHostType->getId(),
                    'HOST_TYPE_LABEL' => $currentHostType->getName(),
                    'HOST_TYPE_SELECTED' => $selected,
                )
            );

            $this->getPage()->parse('host_type_block', 'host_type', true);
        }
    }

    /**
     * Render the dropdown for the start of Leave
     *
     * @param null $startTime
     */
    private function renderStartTimeDropdown($startTime = null)
    {
        $this->getPage()->setBlock(TEMPLATE_content, 'request_start_time', 'request_start_time_block');

        /* Iterate through each option and preselect
           */
        foreach ($this->getTimeMap() as $value => $label) {
            if ($startTime == $value) {
                $selected = 'selected=\'selected\'';
            } else {
                $selected = '';
            }

            $this->getPage()->setVar(
                array(
                    'REQUEST_START_TIME_VALUE' => $value,
                    'REQUEST_START_TIME_LABEL' => $label,
                    'REQUEST_START_TIME_SELECTED' => $selected,
                )
            );

            $this->getPage()->parse('request_start_time_block', 'request_start_time', true);
        }
    }

    /**
     * Render the dropdown for the end of Leave
     *
     * @param null $finishTime
     */
    private function renderFinishTimeDropdown($finishTime = null)
    {
        $this->getPage()->setBlock(TEMPLATE_content, 'request_finish_time', 'request_finish_time_block');

        /* Iterate through each option and preselect
           */
        foreach ($this->getTimeMap() as $value => $label) {
            if ($finishTime == $value) {
                $selected = 'selected=\'selected\'';
            } else {
                $selected = '';
            }

            $this->getPage()->setVar(
                array(
                    'REQUEST_FINISH_TIME_VALUE' => $value,
                    'REQUEST_FINISH_TIME_LABEL' => $label,
                    'REQUEST_FINISH_TIME_SELECTED' => $selected,
                )
            );


            $this->getPage()->parse('request_finish_time_block', 'request_finish_time', true);
        }
    }

    /**
     * Execute create request
     *
     * @return Array
     */
    public function executeCreateRequest()
    {
        return array();
    }

    /**
     * Renders a create request page
     *
     * @param Array $data
     * @return void
     */
    public function renderCreateRequest($data)
    {
        $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/createRequest.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'student_selector', 'student_selector_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'travel_drop', 'travel_drop_block');
        $this->getPage()->setBlock('student_selector', 'student_drop', 'student_drop_block');
        $this->getPage()->setBlock(
            'custom_head', 'student_autocomplete_javascript', 'student_autocomplete_javascript_block'
        );
        $this->getPage()->setBlock(TEMPLATE_content, 'student_autocomplete', 'student_autocomplete_block');

        /* Render dropdowns
           */
        $request = new Leave_Request();

        $this->renderStartTimeDropdown();
        $this->renderFinishTimeDropdown();
        $this->renderHostTypeDropdown($request);

        $this->getPage()->setVar($data);

        $this->getPage()->dropDown(
            'travel_drop',
            $this->getTravelTypes(),
            null,
            TEMPLATE_content,
            array(
                'name' => 'TRAVEL_DROP_NAME',
                'id'   => 'TRAVEL_DROP_VALUE',
            )
        );

        /* Add terms and conditions
           */
        $this->getPage()->setVar(
            array(
                'TERMS_AND_CONDITIONS' => Cfg::get('terms_and_conditions')
            )
        );

        /* Apply autocomplete if appropriate:
           housemaster users can submit on someone else's behalf
           (as long as they have housemaster permission on the student's house)
           parents can submit on behalf of their students
           Everyone else submits as themselves
           */
        $this->getPage()->setVar('student_selector_block', '');
        $this->getPage()->setVar('student_autocomplete_block', '');
        $this->getPage()->setVar('student_autocomplete_javascript_block', '');

        // Get the houses on which this user has housemaster permission, if any
        // and integrate those into the list of student group IDs
        $residentialHouseIds = array();
        foreach ($this->getHouseMapper()->findAll() as $house) {
            $houseId = $house->getId();
            if ($this->hasApprovePermission($houseId)) {
                $residentialHouseIds[] = $houseId;
            }
        }
        if (!empty($residentialHouseIds)) {
            $this->getPage()->setVar(
                array(
                    'STUDENT_GROUP_IDS'     => implode(';', $this->getGroup()->getStudentGroupTypeIds()),
                    'RESIDENTIAL_HOUSE_IDS' => implode(';', $residentialHouseIds),
                )
            );
            $this->getPage()->parse('student_autocomplete_block', 'student_autocomplete');
            $this->getPage()->parse('student_autocomplete_javascript_block', 'student_autocomplete_javascript');
        } elseif ($this->getGroup()->isParent()) {
            $this->getPage()->dropDown(
                'student_drop',
                $_SESSION['children'],
                null,
                'student_selector',
                array(
                    'name' => 'STUDENT_DROP_NAME',
                    'id'   => 'STUDENT_DROP_VALUE',
                )
            );
            $this->getPage()->parse('student_selector_block', 'student_selector');
        }
    }

    /**
     * Does this user have permission to view requests for a given user?
     *
     * The user has permission if:
     * * They have housemaster privileges for the house that the student is in
     * * They are the parent of the child the request is for
     * * They made the request
     *
     * @param  int  $userId  The user's ID
     * @param  int  $houseId The user's house ID
     * @return bool
     */
    protected function hasViewPermission($userId, $houseId = null)
    {
        return $_SESSION['user']->getId() == $userId
            || $this->getGroup()->getPermission($houseId)
            || $this->getGroup()->isParentOfChild($userId);
    }

    /**
     * Does this user have permission to modify or approve a request?
     *
     * The user has permission if:
     * * They have housemaster privileges for the house that the student is in
     *
     * @param int $houseId What house am I checking permissions for?
     *
     * @return bool
     */
    protected function hasApprovePermission($houseId = null)
    {
        return $this->getGroup()->getPermission($houseId);
    }

    /**
	 * Determine whether the leave start date is inside or outside the
	 * creation threshold. If it's outside, you can create it
	 *
	 * Thresholds are measured from Friday 12PM of the weekend that
	 * leave was intended to start
	 *
	 * @param $startDate
	 * @return bool
	 */
    private function isOutsideCreationThreshold($startDate)
    {
        $thresholdHours = \Cfg::get('boarder_leave_creation_threshold');

        /* Use 0 to deactivate this feature
		 */
        if (!$thresholdHours) {
            return true;
        }

        $thresholdDateTime = \Alaress_Date_Format::newLocalShort($startDate);
        $weekdayIndex = $thresholdDateTime->getWeekDayNumber();

        /* Get back to nearest Friday, which comes after furthest Thursday
		 */
        if ($weekdayIndex == 6) {
            $thresholdDateTime->addDays(-1);
        } elseif ($weekdayIndex == 7) {
            $thresholdDateTime->addDays(-2);
        }

        /* Subtract number of hours from config to determine outer limits
		 * Measure it from high noon
		 */
        $thresholdDateTime->addHours(12 - $thresholdHours);

        /* If you're outside the threshold, you can create it
		 */
        if ($_SERVER['REQUEST_TIME'] < $thresholdDateTime->getUnixTimestamp()) {
            return true;
        }

        return false;
    }

    /**
     * Processes form data submitted from renderCreateRequest
     *
     * @param Array $data
     *
     * @return void
     */
    public function processCreateRequest($data)
    {
        if (!is_array($data) || empty($data['request'])) {
            $this->getPage()->error(2);
        }

        /* Limit leave creation to a threshold
		 */
        if ($this->isOutsideCreationThreshold($data['request']['start']['date'])) {
            $start = Alaress_Date_Format::newLocalShort($data['request']['start']['date'])
                ->modify($data['request']['start']['time'])
            ;
            $finish = Alaress_Date_Format::newLocalShort($data['request']['finish']['date'])
                ->modify($data['request']['finish']['time'])
            ;
            $created = Alaress_Date_Format::now();
            $request = new Leave_Request();

            /* Use the userId field when submitting on someone else's behalf,
			 * otherwise just your own id
			 */
            $userId = $data['userId'];
            try {
                $user = $this->getUserMapper()->findById($userId);
            } catch (Schoolbox\Mapper\Exception $e) {
                throw new Exception_Schoolbox_NotFound('User not found');
            }
            $request->setUser(
                ($this->hasViewPermission($userId, $user->getResidentialHouse())
                    ? $userId
                    : $_SESSION['user']->getId())
            );
            $request->setAuthor($_SESSION['user']);
            $request->setStart($start->getDatabase());
            $request->setFinish($finish->getDatabase());
            $request->setHostType($data['request']['hostType']);
            $request->setHostName($data['request']['hostName']);
            $request->setHostAddress($data['request']['hostAddress']);
            $request->setHostPhone($data['request']['hostPhone']);
            $request->setHostMobile($data['request']['hostMobile']);
            $request->setUserPhone($data['request']['userPhone']);
            $request->setReason($data['request']['reason']);
            $request->setDescription($data['request']['description']);
            $request->setCollectorName($data['request']['collectorName']);
            $request->setReturnerName($data['request']['returnerName']);
            $request->setStatus(Leave_Request::STATUS_PENDING);
            $request->setCreated($created->getDatabase());

            $travelType  = $data['request']['travelType'];
            $travelTypes = $this->getTravelTypes();

            if ($travelType && array_key_exists($travelType, $travelTypes)) {
                $request->setTravelType($travelTypes[$travelType]);
            }

            $this->getRequestMapper()->save($request);

            $request = $this->getRequestMapper()->findById($request->getId());

            $this->sendUserNotice($request);
            $this->sendParentNotice($request);
            $this->sendAdminNotice($request);

            SitePage::localRedir("/modules/leave/student/{$request->getUserId()}");
        } else {
            error(162);
        }
    }

    /**
     * Returns a data array with the request to be modified
     *
     * @param Integer $requestId
     * @return Array
     * @throws Exception_Schoolbox_NotFound if the request doesn't exist.
     */
    public function executeModifyRequest($requestId)
    {
        try {
            $request = $this->getRequestMapper()->findById($requestId);
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new Exception_Schoolbox_NotFound('Request not found');
        }
        return array('request' => $request);
    }

    /**
     * Renders a modify request form
     *
     * @param Array $data
     * @return void
     * @throws Exception_Schoolbox_NotFound if the requester's house doesn't exist.
     */
    public function renderModifyRequest($data)
    {
        /** @var Leave_Request $request */
        $request = $data['request'];
        $user = $request->getUser();
        if (!$user instanceof \Schoolbox\User) {
            throw new Exception_Schoolbox_NotFound('User not found');
        }
        try {
            $house = $this->getHouseMapper()->findById($user->getResidentialHouse());
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new Exception_Schoolbox_NotFound('User\'s house not found');
        }
        if (!$this->hasApprovePermission($house->getId())) {
            error(19);
        }

        $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/modifyRequest.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'next_pending', 'next_pending_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'prev_pending', 'prev_pending_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'travel_drop', 'travel_drop_block');
        $this->getPage()->dropDown(
            'travel_drop',
            $this->getTravelTypes(),
            $request->getTravelType()->getId(),
            TEMPLATE_content,
            array(
                'name' => 'TRAVEL_DROP_NAME',
                'id'   => 'TRAVEL_DROP_VALUE',
            )
        );

        /* Render dropdowns
            */
        $this->renderStartTimeDropdown($request->getStart()->getDatabaseTime());
        $this->renderFinishTimeDropdown($request->getFinish()->getDatabaseTime());
        $this->renderHostTypeDropdown($request);
        $this->renderConfirmedByCheckboxes($request);

        $this->getPage()->setVar(array(
            'REQUEST_ID' => $request->getId(),
            'REQUEST_STATUS' => $request->getStatus(true),
            'AUTHOR_NAME' => $request->getAuthor()->formatName(),
            'USER_ID' => $user->getId(),
            'USER_NAME' => $user->formatName(),
            'USER_EXTERNAL_ID' => $user->getExternalId(),
            'USER_YEAR' => $user->getYear(),
            'USER_HOUSE' => Scrub::text($house->getHouse()),
            'USER_HOUSE_ID' => $house->getId(),
            'REQUEST_START_DATE' => $request->getStart()->getLocalShort(),
            'REQUEST_FINISH_DATE' => $request->getFinish()->getLocalShort(),
            'REQUEST_HOST_NAME' => $request->getHostName(),
            'REQUEST_HOST_PHONE' => $request->getHostPhone(),
            'REQUEST_HOST_MOBILE' => $request->getHostMobile(),
            'REQUEST_HOST_ADDRESS' => $request->getHostAddress(),
            'REQUEST_USER_PHONE' => $request->getUserPhone(),
            'REQUEST_DESCRIPTION' => $request->getDescription(),
            'REQUEST_NOTES' => $request->getNotes(),
            'REQUEST_COLLECTOR_NAME' => $request->getCollectorName(),
            'REQUEST_RETURNER_NAME' => $request->getReturnerName(),
            'REQUEST_REASON' => $request->getReason(),
        ));

        /* What is the next/previous pending request?
         */
        $nextRequest = $this->getRequestMapper()->findNextPendingByHouseId(
            $request->getId(), $user->getResidentialHouse()
        );

        if ($nextRequest) {
            $this->getPage()->setVar(array(
                'NEXT_PENDING_ID' => $nextRequest->getId()
            ));

            $this->getPage()->parse('next_pending_block', 'next_pending');
        } else {
            $this->getPage()->setVar('next_pending_block', '');
        }

        $previousRequest = $this->getRequestMapper()->findPreviousPendingByHouseId(
            $request->getId(), $user->getResidentialHouse()
        );

        if ($previousRequest) {
            $this->getPage()->setVar(
                array(
                    'PREV_PENDING_ID' => $previousRequest->getId()
                )
            );

            $this->getPage()->parse('prev_pending_block', 'prev_pending');
        } else {
            $this->getPage()->setVar('prev_pending_block', '');
        }
    }

    /**
     * Renders a view leave request form
     *
     * @param Array $data
     * @return void
     * @throws Exception_Schoolbox_NotFound if the user or their house doesn't exist.
     */
    public function renderViewRequest($data)
    {
        /* Allow the user and staff to view requests
         */

        /** @var Leave_Request $request */
        $request = $data['request'];
        $user = $request->getUser();
        if (!$user instanceof \Schoolbox\User) {
            throw new Exception_Schoolbox_NotFound('User not found');
        }
        try {
            $house = $this->getHouseMapper()->findById($user->getResidentialHouse());
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new Exception_Schoolbox_NotFound('User\'s house not found');
        }

        if ($this->hasViewPermission($user->getId(), $house->getId())) {

            $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/viewRequest.html');

            $travelTypes = $this->getTravelTypes();
            $travelType  = $travelTypes[$request->getTravelType()->getId()] ?: $travelTypes[0];
            $this->getPage()->setVar(
                array(
                    'REQUEST_ID' => $request->getId(),
                    'REQUEST_STATUS' => $request->getStatus(true),
                    'USER_NAME' => $request->getAuthor()->formatName(),
                    'USER_EXTERNAL_ID' => $user->getExternalId(),
                    'USER_YEAR' => $user->getYear(),
                    'USER_HOUSE' => Scrub::text($house->getHouse()),
                    'USER_HOUSE_ID' => $house->getId(),
                    'HOST_TYPE' => $this->getHostTypes()->getBy('id', $request->getHostType()->getId())->getName(),
                    'REQUEST_START_DATE' => $request->getStart()->getLocalShort(),
                    'REQUEST_START_TIME' => $request->getStart()->getTimeOnly(),
                    'REQUEST_FINISH_DATE' => $request->getFinish()->getLocalShort(),
                    'REQUEST_FINISH_TIME' => $request->getFinish()->getTimeOnly(),
                    'REQUEST_HOST_NAME' => $request->getHostName(),
                    'REQUEST_HOST_PHONE' => $request->getHostPhone(),
                    'REQUEST_HOST_MOBILE' => $request->getHostMobile(),
                    'REQUEST_HOST_ADDRESS' => $request->getHostAddress(),
                    'REQUEST_USER_PHONE' => $request->getUserPhone(),
                    'REQUEST_DESCRIPTION' => $request->getDescription(),
                    'REQUEST_COLLECTOR_NAME' => $request->getCollectorName(),
                    'REQUEST_RETURNER_NAME' => $request->getReturnerName(),
                    'REQUEST_REASON' => $request->getReason(),
                    'REQUEST_NOTES' => $request->getNotes(),
                    'REQUEST_TRAVEL_TYPE' => $travelType->getName(),
                )
            );
        } else {
            error(1);
        }
    }

    /**
     * Processes a modify form for a request
     *
     * @param Array $data
     * @return void
     * @throws Exception_Schoolbox_NotFound if request isn't found.
     */
    public function processModifyRequest($data)
    {
        /** @var Leave_Request $request */
        try {
            $request = $this->getRequestMapper()->findById($data['request']['id']);
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new Exception_Schoolbox_NotFound('Request not found');
        }

        $start = new Alaress_Date_Format("{$data['request']['start']['date']} {$data['request']['start']['time']}", 'd/m/Y H:i:s');
        $finish = new Alaress_Date_Format("{$data['request']['finish']['date']} {$data['request']['finish']['time']}", 'd/m/Y H:i:s');

        $request->setStart($start->getDatabase());
        $request->setFinish($finish->getDatabase());
        $request->setHostType($data['request']['hostType']);
        $request->setHostName($data['request']['hostName']);
        $request->setHostPhone($data['request']['hostPhone']);
        $request->setHostMobile($data['request']['hostMobile']);
        $request->setHostAddress($data['request']['hostAddress']);
        $request->setUserPhone($data['request']['userPhone']);
        $request->setDescription($data['request']['description']);
        $request->setNotes($data['request']['notes']);
        $request->setConfirmedBy($data['request']['confirmedBy']);
        $request->setReturnerName($data['request']['returnerName']);
        $request->setCollectorName($data['request']['collectorName']);
        $request->setReason($data['request']['reason']);

        $travelType  = $data['request']['travelType'];
        $travelTypes = $this->getTravelTypes();
        if ($travelType && array_key_exists($travelType, $travelTypes)) {
            $request->setTravelType($travelTypes[$travelType]);
        } else {
            $request->setTravelType(null);
        }

        if (isset($data['approve']) || isset($data['reject'])) {
            $authorisationDatetime = new Alaress_Date_Format(time(), 'U');

            $request->setAuthorisationDatetime($authorisationDatetime->getDatabase());
            $request->setAuthorisationUser($_SESSION['user']);

            if (isset($data['approve'])) {
                $request->setStatus(Leave_Request::STATUS_APPROVED);
            } elseif (isset($data['reject'])) {
                $request->setStatus(Leave_Request::STATUS_REJECTED);
            }
        } else {
            if (isset($data['delete'])) {
                $request->setStatus(Leave_Request::STATUS_DELETED);
            }
        }


        $this->getRequestMapper()->save($request);

        $request = $this->getRequestMapper()->findById($request->getId());

        if (isset($data['approve']) || isset($data['reject'])) {
            $this->sendUserNotice($request);
            $this->sendParentNotice($request);
            $this->sendAdminNotice($request);
        }

        $this->getPage()->backToTheFuture(2);
    }

    /**
     * Returns a collection of approved requests by house id
     *
     * @param Integer $houseId
     *
     * @return Array contains these keys:
     *               * house: the HouseResidential object
     *               * requestList: the requests for this house
     *
     * @throws Exception_Schoolbox_NotFound if the house $houseID doesn't exist.
     */
    public function executeHouseRequestList($houseId)
    {
        try {
            $house = $this->getHouseMapper()->findById($houseId);
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new Exception_Schoolbox_NotFound('House not found');
        }
        $requestList = $this->getRequestMapper()->findAllByHouseId($house->getId());

        return array(
            'house' => $house,
            'requestList' => $requestList,
        );
    }

    public function exportHouseRequestList($data)
    {
        $house = $data['house'];
        $requestList = $data['requestList'];
        $allRows = array();

        $headings = array(
            'User ID',
            'Firstname',
            'Lastname',
            'House',
            'Year',
            'Start',
            'Finish',
            'Host Type',
            'Status',
            'Status Updated On',
            'Status Updated By'
        );

        $allRows[] = TextFormatter::outputCSV($headings);

        foreach ($requestList as $request) {
            if ($request->getStatus() == Leave_Request::STATUS_APPROVED) {
                $rowData = array();

                $rowData[] = $request->getUser()->getId();
                $rowData[] = $request->getUser()->getFirstname();
                $rowData[] = $request->getUser()->getLastname();
                $rowData[] = $house->getHouse();
                $rowData[] = $request->getUser()->getYear();
                $rowData[] = $request->getStart()->getLocal();
                $rowData[] = $request->getFinish()->getLocal();
                $rowData[] = $request->getHostType()->getName();
                $rowData[] = $request->getStatus(true);
                $rowData[] = $request->getAuthorisationDatetime(true)->getLocal();
                $rowData[] = $request->getAuthorisationUser()->getFirstname() . ' ' . $request->getAuthorisationUser(
                )->getLastname();

                $allRows[] = TextFormatter::outputCSV($rowData);
            }
        }

        $this->dumpCSV("Leave Requests for {$house->getHouse()}", $allRows);
    }

    /**
     * Dump a csv file's contents w/ headers
     *
     * @param $filename
     * @param $allRows
     */
    private function dumpCSV($filename, $allRows)
    {
        $name = new Url($filename);

        header('Content-type: application/csv');
        header("Content-Disposition: attachment; filename={$name->getSlug()}.csv");
        header("Pragma: no-cache");
        header("Expires: 0");

        echo implode("\n", $allRows);
        exit(1);
    }

    /**
     * Renders a collection of requests by house
     *
     * @param Array $data
     * @return void
     */
    public function renderHouseRequestList($data)
    {
        $house = $data['house'];
        $requestList = $data['requestList'];

        $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/houseRequestList.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'no_requests', 'no_requests_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'pending_request_list', 'pending_request_list_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'previous_request_list', 'previous_request_list_block');
        $this->getPage()->setBlock('pending_request_list', 'pending_request', 'pending_request_block');
        $this->getPage()->setBlock('previous_request_list', 'previous_request', 'previous_request_block');

        $this->getPage()->setVar(
            array(
                'HOUSE_ID' => $house->getId(),
                'HOUSE_NAME' => Scrub::text($house->getHouse())
            )
        );

        if (count($requestList)) {
            $this->getPage()->setVar('no_requests_block', '');

            $pendingRequestCount = 0;
            $previousRequestCount = 0;

            foreach ($requestList as $request) {
                $this->getPage()->setVar(array(
                    'USER_ID' => $request->getUser()->getId(),
                    'USER_NAME' => $request->getUser()->formatName(),
                    'USER_YEAR' => $request->getUser()->getYear(),
                    'USER_BOARDING_STATUS' => $request->getUser()->getAbbreviatedBoardingStatus(),
                    'REQUEST_ID' => $request->getId(),
                    'REQUEST_START' => $request->getStart()->getLocal(),
                    'REQUEST_DURATION' => $request->getDuration(),
                    'REQUEST_HOST_TYPE' => $this->getHostTypes()->getBy('id', $request->getHostType()->getId())->getName()
                ));

                if ($request->getStatus() == Leave_Request::STATUS_PENDING) {
                    $this->getPage()->parse('pending_request_block', 'pending_request', true);

                    $pendingRequestCount++;
                } else {
                    $this->getPage()->setVar(
                        array(
                            'REQUEST_STATUS' => $request->getStatus(true),
                            'REQUEST_AUTHORISATION_DATETIME' => $request->getAuthorisationDatetime(true)->getLocal(),
                            'REQUEST_AUTHORISATION_USER_ID' => $request->getAuthorisationUser()->getId(),
                            'REQUEST_AUTHORISATION_USER_NAME' => $request->getAuthorisationUser()->formatName(),
                        )
                    );

                    $this->getPage()->parse('previous_request_block', 'previous_request', true);

                    $previousRequestCount++;
                }
            }

            if ($pendingRequestCount) {
                $this->getPage()->parse('pending_request_list_block', 'pending_request_list');
            } else {
                $this->getPage()->setVar('pending_request_list_block', '');
            }

            if ($previousRequestCount) {
                $this->getPage()->parse('previous_request_list_block', 'previous_request_list');
            } else {
                $this->getPage()->setVar('previous_request_list_block', '');
            }
        } else {
            $this->getPage()->setVar('pending_request_list_block', '');
            $this->getPage()->setVar('previous_request_list_block', '');

            $this->getPage()->parse('no_requests_block', 'no_requests');
        }
    }

    /**
     * Returns a collection of requests for a user
     *
     * @param $userId
     * @return array
     * @throws Exception_Schoolbox_NotFound if user or their house isn't found.
     */
    public function executeUserRequestList($userId)
    {
        try {
            $user = $this->getUserMapper()->findById($userId);
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new Exception_Schoolbox_NotFound('User not found');
        }
        try {
            $house = $this->getHouseMapper()->findById($user->getResidentialHouse());
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new Exception_Schoolbox_NotFound('User\'s house not found');
        }
        $requestList = $this->getRequestMapper()->findAllByUserId($user->getId());

        return array(
            'user' => $user,
            'house' => $house,
            'requestList' => $requestList,
        );
    }

    /**
     * Export a csv of leave requests belonging to a particular student
     *
     * @param $data
     */
    public function exportUserRequestList($data)
    {
        /** @var Schoolbox\User $user */
        $user = $data['user'];
        if ($this->hasViewPermission($user->getId(), $user->getResidentialHouse())) {
            /** @var Leave_Request[] $requestList */
            $requestList = $data['requestList'];
            $allRows = array();

            $headings = array(
                'User ID',
                'Name',
                'Year',
                'Start',
                'Finish',
                'Host Type',
                'Status',
                'Authorised Date',
                'Authorising User'
            );

            $allRows[] = TextFormatter::outputCSV($headings);

            /* Only provide a history of approved leave requests
                */
            foreach ($requestList as $request) {
                if ($request->getStatus() == Leave_Request::STATUS_APPROVED) {
                    $rowData = array();
                    $rowData[] = $request->getUser()->getId();
                    $rowData[] = $request->getUser()->formatName();
                    $rowData[] = $request->getUser()->getYear();
                    $rowData[] = $request->getStart()->getLocal();
                    $rowData[] = $request->getFinish()->getLocal();
                    $rowData[] = $request->getHostType()->getName();
                    $rowData[] = $request->getStatus(true);
                    $rowData[] = $request->getAuthorisationDatetime(true)->getLocal();
                    $rowData[] = $request->getAuthorisationUser()->formatName();

                    $allRows[] = TextFormatter::outputCSV($rowData);
                }
            }

            $this->dumpCSV('Leave Requests - ' . $user->formatName(), $allRows);
        }

        exit(1);
    }

    /**
     * Renders a collection of request for a user
     *
     * @param $data
     * @return void
     */
    public function renderUserRequestList($data)
    {
        /* Only allow students to see their own leave requests
           * Allow staff/superusers to see anyone's
           */
        /** @var \Schoolbox\User $user */
        $user = $data['user'];
        $house = $data['house'];
        $requestList = $data['requestList'];
        if (!$user instanceof \Schoolbox\User) {
            throw new Exception_Schoolbox_NotFound('User not found');
        }
        if (!$house instanceof HouseResidential) {
            throw new Exception_Schoolbox_NotFound('User\'s house not found');
        }
        if (!($this->hasViewPermission($user->getId(), $house->getId()))) {
            error(19);
        }

        $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/userRequestList.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'no_requests', 'no_requests_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'pending_request_list', 'pending_request_list_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'previous_request_list', 'previous_request_list_block');
        $this->getPage()->setBlock('pending_request_list', 'pending_request', 'pending_request_block');
        $this->getPage()->setBlock('previous_request_list', 'previous_request', 'previous_request_block');
        $this->getPage()->setBlock('previous_request_list', 'export', 'export_block');

        if ($this->hasApprovePermission($house->getId())) {
            $this->getPage()->parse('export_block', 'export');
        } else {
            $this->getPage()->setVar('export_block', '');
        }

        $this->getPage()->setVar(
            array(
                'USER_ID' => $user->getId(),
                'USER_NAME' => $user->formatName(),
                'HOUSE_NAME' => Scrub::text($house->getHouse()),
                'HOUSE_ID' => $house->getId(),
                'ALLOWED_ACTION' => $this->hasApprovePermission($house->getId()) ? 'modify' : 'view',
            )
        );

            /* Render each request
                */
            if (count($requestList)) {
                $this->getPage()->setVar('no_requests_block', '');

                $pendingRequestCount = 0;
                $previousRequestCount = 0;

                foreach ($requestList as $request) {
                    $this->getPage()->setVar(
                        array(
                            'USER_ID' => $request->getUser()->getId(),
                            'USER_NAME' => $request->getUser()->formatName(),
                            'USER_YEAR' => $request->getUser()->getYear(),
                            'REQUEST_ID' => $request->getId(),
                            'REQUEST_START' => $request->getStart()->getLocal(),
                            'REQUEST_DURATION' => $request->getDuration(),
                            'REQUEST_HOST_TYPE' => $request->getHostType()->getName()
                        )
                    );

                    if ($request->getStatus() == Leave_Request::STATUS_PENDING) {
                        $this->getPage()->parse('pending_request_block', 'pending_request', true);

                        $pendingRequestCount++;
                    } else {
                        $this->getPage()->setVar(
                            array(
                                'REQUEST_STATUS' => $request->getStatus(true),
                                'REQUEST_AUTHORISATION_DATETIME' => $request->getAuthorisationDatetime(true)->getLocal(),
                                'REQUEST_AUTHORISATION_USER_ID' => $request->getAuthorisationUser()->getId(),
                                'REQUEST_AUTHORISATION_USER_NAME' => $request->getAuthorisationUser()->formatName(),
                            )
                        );

                        $this->getPage()->parse('previous_request_block', 'previous_request', true);

                        $previousRequestCount++;
                    }
                }

                if ($pendingRequestCount) {
                    $this->getPage()->parse('pending_request_list_block', 'pending_request_list');
                } else {
                    $this->getPage()->setVar('pending_request_list_block', '');
                }

                if ($previousRequestCount) {
                    $this->getPage()->parse('previous_request_list_block', 'previous_request_list');
                } else {
                    $this->getPage()->setVar('previous_request_list_block', '');
                }
            } else {
                $this->getPage()->parse('no_requests_block', 'no_requests');

                $this->getPage()->setVar('pending_request_list_block', '');
                $this->getPage()->setVar('previous_request_list_block', '');
            }
//        else {
//            error(19);
//        }
    }

    /**
     * Processes modifications against a list of requests
     *
     * As far as I can tell, this is never used.
     *
     * @param Array $data
     * @return void
     * @throws Exception_Schoolbox_NotFound if request isn't found
     */
    public function processModifyRequestList($data)
    {
        /* Only allow Teachers/Staff to approve/reject requests
         */
        try {
            $user = $this->getUserMapper()->findById($data['user']['id']);
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new Exception_Schoolbox_NotFound('User not found');
        }
        try {
            $house = $this->getHouseMapper()->findById($user->getResidentialHouse());
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new Exception_Schoolbox_NotFound('User\'s house not found');
        }
        if (!$this->hasApprovePermission($house->getId())) {
            error(19);
        }

        $requestList = $data['requestList'];

        if (!empty($requestList)) {
            foreach ($requestList as $request) {
                /** @var Leave_Request $request */
                try {
                    $request = $this->getRequestMapper()->findById($request['id']);
                } catch (Schoolbox\Mapper\Exception $e) {
                    throw new Exception_Schoolbox_NotFound('Request not found');
                }

                if (isset($data['approve']) || isset($data['reject'])) {
                    $authorisationDatetime = new Alaress_Date_Format(time(), 'U');

                    $request->setAuthorisationDatetime($authorisationDatetime->getDatabase());
                    $request->setAuthorisationUser($_SESSION['user']);

                    if (isset($data['approve'])) {
                        $request->setStatus(Leave_Request::STATUS_APPROVED);
                    } elseif (isset($data['reject'])) {
                        $request->setStatus(Leave_Request::STATUS_REJECTED);
                    }
                }

                $this->getRequestMapper()->save($request);

                if (isset($data['approve']) || isset($data['reject'])) {
                    $request = $this->getRequestMapper()->findById($request->getId());

                    $this->sendUserNotice($request);
                    $this->sendParentNotice($request);
                    $this->sendAdminNotice($request);
                }
            }
        }

        $this->getPage()->localRedir("/modules/leave/student/{$user->getId()}");
    }

    /**
     * Render confirmed by checkbox array
     * Knows whether to show them depending on user type
     *
     * @param $request
     */
    private function renderConfirmedByCheckboxes($request)
    {
        $this->getPage()->setBlock(TEMPLATE_content, 'confirmed_by', 'confirmed_by_block');

        /* Iterate through options
           */
        if ($this->hasApprovePermission($request->getUser()->getResidentialHouse())) {
            foreach ($request->getConfirmedByMap() as $value => $label) {
                if ($request->getConfirmedBy() == $value) {
                    $selected = 'checked=\'checked\'';
                } else {
                    $selected = '';
                }

                $this->getPage()->setVar(
                    array(
                        'CONFIRMED_BY_VALUE' => $value,
                        'CONFIRMED_BY_LABEL' => $label,
                        'CONFIRMED_BY_SELECTED' => $selected,
                    )
                );

                $this->getPage()->parse('confirmed_by_block', 'confirmed_by', true);
            }
        } else {
            $this->getPage()->setVar('has_confirmed_by_block', '');
        }
    }

    /**
     * Get a list of leave requests between specified dates
     *
     * @param $houseId
     * @param $date
     * @return array
     * @throws Exception_Schoolbox_NotFound if house isn't found
     */
    public function executeHouseReport($houseId, $date)
    {
        try {
            $house = $this->getHouseMapper()->findById($houseId);
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new Exception_Schoolbox_NotFound('House not found');
        }

        /* Mess with these two magic numbers to change the range, in conjunction
           * with the class constant RANGE
           */
        $reportStart = Alaress_Date_Format::newLocalShort($date);
        $reportStart->addDays(self::FRIDAY - $reportStart->getWeekDayNumber());

        $reportFinish = Alaress_Date_Format::newLocalShort($date);
        $reportFinish->addDays(self::SUNDAY - $reportFinish->getWeekDayNumber());
        $reportFinish->addHours(24);

        $requestList = $this->getRequestMapper()->getHouseReport(
            $houseId, $reportStart->getDatabaseDate(), $reportFinish->getDatabaseDate()
        );

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
    public function renderHouseReport($data)
    {
        /* Can wrap this in a factory to provide school-level customisation
           */
        $factory = new Leave_Report_House_Factory($this->getPage(), $data, self::RANGE);
        $factory->produceWeb()->render();
    }

    /**
     * Export CSV of renderHouseReport()
     *
     * @param $data
     */
    public function exportHouseReport($data)
    {
        /* Can wrap this in a factory to provide school-level customisation
           */
        $factory = new Leave_Report_House_Factory($this->getPage(), $data, self::RANGE);
        $factory->produceCSV()->render();

        die();
    }

    /**
     * Export CSV of renderHouseReport()
     *
     * @param $data
     */
    public function printHouseReport($data)
    {
        /* Can wrap this in a factory to provide school-level customisation
           */
        $factory = new Leave_Report_House_Factory($this->getPage(), $data, self::RANGE);
        $factory->producePrint()->render();
    }

    /**
     * Get catering requirements for the Friday, Saturday and Sunday.
     * This is the period of time when people can go on leave.
     *
     * @param $date
     * @return array
     */
    public function executeCateringReport($date)
    {
        /* Collate results for each day
           */
        $data = array();
        $data['date'] = $date;

        /* Skip Friday
           */
        for ($i = self::SATURDAY; $i <= self::SUNDAY; $i++) {
            $currentDate = Alaress_Date_Format::newLocalShort($date);
            $currentDate->addDays($i - $currentDate->getWeekDayNumber());

            $data['dateList'][] = $currentDate;

            $allHouses = $this->getRequestMapper($date)->getCateringReport($currentDate);
            $data['houseList'][] = $allHouses;

            /* Summate totals for each house in this segment
                */
            $totals = array();

            foreach ($allHouses as $currentHouse) {
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
    public function renderCateringReport($data)
    {
        $report = new Leave_Report_Catering_Web($this->getPage(), $data);
        $report->render();
    }

    /**
     * Export a csv version of the catering report. Algorithm is similar to
     * renderCateringReport, but due to non-templating things have to be
     * fputcsv'd in order
     *
     * @param $data
     */
    public function exportCateringReport($data)
    {
        $report = new Leave_Report_Catering_CSV($this->getPage(), $data);
        $report->render();
    }

    /**
     * Send email notification
     *
     * @param $request
     */
    public function sendUserNotice($request)
    {
        /* Choose the Factory, have it produce() the notice object and send
         */
        $leaveFactory = new Leave_Notice_Factory($request);
        $leaveFactory->produceUserFactory()->produce()->send();
    }

    /**
     * Send a notice to a parent when someone creates a leave request
     *
     * At Kings Live, parent_notification started saving as integer 1/0, instead
     * of the usual true/false string?
     *
     * @param $request
     */
    public function sendParentNotice($request)
    {
        if ((Cfg::get('parent_notification') == 0) || (Cfg::get('parent_notification') == 'false')) {

            /* Choose the Factory, have it produce() the notice object and send
                */
            $leaveFactory = new Leave_Notice_Factory($request);
            $leaveFactory->produceParentFactory()->produce()->send();
        }
    }

    /**
     * Send a notice to a parent when someone creates a leave request
     *
     * At Kings Live, parent_notification started saving as integer 1/0, instead
     * of the usual true/false string?
     *
     * @param $request
     */
    public function sendAdminNotice($request)
    {
        $leaveFactory = new Leave_Notice_Factory($request);
        $leaveFactory->produceAdminFactory()->produce()->send();
    }

    /* Getters/Setters
      */

    /**
     * Set external user
     *
     * @param $externalUser
     */
    public function setExternalUser($externalUser)
    {
        $this->externalUser = $externalUser;
    }

    /**
     * Get external user
     *
     * @return mixed
     */
    public function getExternalUser()
    {
        return $this->externalUser;
    }

    /**
     * Set group handler
     *
     * @param Group $group
     */
    private function setGroup($group)
    {
        $this->group = $group;
    }

    /**
     * Get group handler
     *
     * @return Group
     */
    private function getGroup()
    {
        return $this->group;
    }

    /**
     * Setter for $requestMapper
     *
     * @param Leave_RequestMapper $requestMapper
     *
     * @return void
     */
    protected function setRequestMapper($requestMapper)
    {
        $this->requestMapper = $requestMapper;
    }

    /**
     * Getter for $requestMapper
     *
     * @return Leave_RequestMapper
     */
    protected function getRequestMapper()
    {
        return $this->requestMapper;
    }

    /**
     * Setter for $userMapper
     *
     * @param \Schoolbox\UserMapper $userMapper
     *
     * @return void
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
    }

    /**
     * Getter for $userMapper
     *
     * @return \Schoolbox\UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * Setter for $houseMapper
     *
     * @param HouseMapper $houseMapper
     *
     * @return void
     */
    public function setHouseMapper($houseMapper)
    {
        $this->houseMapper = $houseMapper;
    }

    /**
     * Getter for $houseMapper
     *
     * @return HouseMapper
     */
    public function getHouseMapper()
    {
        return $this->houseMapper;
    }

    /**
     * Set time map
     *
     * @param $timeMap
     */
    private function setTimeMap($timeMap)
    {
        $this->timeMap = $timeMap;
    }

    /**
     * Get time map
     *
     * @return array
     */
    private function getTimeMap()
    {
        return $this->timeMap;
    }

    /**
     * @param  array $travelTypes
     * @return $this
     */
    public function setTravelTypes($travelTypes)
    {
        $this->travelTypes = $travelTypes;
        return $this;
    }

    /**
     * @return array
     */
    public function getTravelTypes()
    {
        return $this->travelTypes;
    }

    /**
     * @param  \Schoolbox\Collection|\Leave\HostType[] $hostTypes
     * @return $this
     */
    public function setHostTypes($hostTypes) {
        $this->hostTypes = $hostTypes;
        return $this;
    }

    /**
     * @return \Schoolbox\Collection|\Leave\HostType[]
     */
    public function getHostTypes() {
        return $this->hostTypes;
    }
}