<?php

/**
 * Leave Controller
 *
 * @author      Alex Tworowsky <aetworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Application\Exception as ApplicationException;
use Schoolbox\Error;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Admin\EmailDKIM\Mapper as DKIMMapper;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Mail\EmailFactory;

class Leave_Controller extends Controller
{
    /**
     * Unused constants which document when morning, day and night occur
     * for Leave Reports and Catering Reports respectively
     */
    const LEAVE_THRESHOLD_MORNING    =  '9:59:59';
    const LEAVE_THRESHOLD_DAY        = '18:59:59';
    const LEAVE_THRESHOLD_NIGHT      = '21:59:59';

    const CATERING_THRESHOLD_MORNING = '11:59:59';
    const CATERING_THRESHOLD_DAY     = '16:59:59';
    const CATERING_THRESHOLD_NIGHT   = '23:59:59';

    /**
     * What sort of time period does a report display.
     */
    const REPORT_TIMESPAN_DAY     = 'day';     // Single day          - eg '15/12/2016'
    const REPORT_TIMESPAN_WEEKEND = 'weekend'; // Weekend starting on - eg '15/12/2016'
    const REPORT_TIMESPAN_RANGE   = 'range';   // Two arbitrary dates - eg '15/12/2016' to '23/12/2016'

    protected static $timespanOptions = [
        self::REPORT_TIMESPAN_DAY,
        self::REPORT_TIMESPAN_WEEKEND,
        self::REPORT_TIMESPAN_RANGE
    ];

    /**
     * Constants for days
     */
    const FRIDAY   = 5;
    const SATURDAY = 6;
    const SUNDAY   = 7;

    /**
     * @var \Leave_RequestMapper
     */
    protected $requestMapper;

    /**
     * @var \Schoolbox\UserMapper
     */
    protected $userMapper;

    /**
     * @var \HouseResidentialMapper
     */
    protected $houseMapper;

    /**
     * External_User handler instance
     *
     * @var External_User
     */
    protected $externalUser;

    /**
     * A grouphandler instance
     *
     * @var \Group
     */
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
     * @param SitePage $page
     */
    public function __construct(SitePage $page)
    {
        parent::__construct($page);

        $this->setUserMapper(\Schoolbox\UserMapper::getInstance());
        $this->setRequestMapper(Leave_RequestMapper::getInstance());
        $this->setHouseMapper(HouseResidentialMapper::getInstance());
        $this->setExternalUser(new External_User());
        $this->setGroup(new Group(UserAccess::SERVICE_HOUSEMASTER));
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


    public function renderSummary()
    {
        if (!$this->hasReportingPermission()) {
            error(Error\Mapper::ERROR_PERM_PAGE_VIEW);
        }

        $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/viewSummary.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'summary_house', 'summary_house_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'reports_menu', 'reports_menu_block');

        $today = Alaress_Date_Format::now();

        $allHouses = $this->getRequestMapper()->getCateringReport($today);

        $leavingToday = 0;
        $returningToday = 0;

        foreach ($allHouses as $house) {
            //Getting a number of absent student from whatever is available
            $absentToday = 0;
            if ($house['absent_morning'] > 0 || $house['absent_day'] > 0 || $house['absent_night'] > 0) {
                $absentToday = max(array($house['absent_morning'], $house['absent_day'], $house['absent_night']));
            }

            $requestList = $this->getRequestMapper()->findAllByHouseId($house['houseId']);
            $totalApproved = 0;
            $totalRejected = 0;
            $totalPending = 0;

            foreach ($requestList as $request) {
                if ($request->isApproved()) {
                    $totalApproved++;
                    if ($request->getStart()->isToday()) {
                        $leavingToday++;
                    }
                    if ($request->getFinish()->isToday()) {
                        $returningToday++;
                    }
                } elseif ($request->isRejected()) {
                    $totalRejected++;
                } else {
                    $totalPending++;
                }
            }

            $this->getPage()->setVar(
                array(
                    'SUMMARY_HOUSE_ID' => $house['houseId'],
                    'SUMMARY_HOUSE_NAME' => $house['name'],
                    'SUMMARY_HOUSE_PENDING' => $totalPending,
                )
            );
            $this->getPage()->parse('reports_menu_block', 'reports_menu', true);


            $this->getPage()->setVar(
                array(
                    'SUMMARY_HOUSE_ID' => $house['houseId'],
                    'SUMMARY_HOUSE_NAME' => $house['name'],
                    'SUMMARY_HOUSE_TOTAL' => $house['absent_morning'] + $house['present_morning'],
                    'SUMMARY_HOUSE_ONLEAVE' => $absentToday,
                    'SUMMARY_HOUSE_APPROVED' => $totalApproved,
                    'SUMMARY_HOUSE_PENDING' => $totalPending,
                    'SUMMARY_HOUSE_REJECTED' => $totalRejected,
                )
            );

            $this->getPage()->parse('summary_house_block', 'summary_house', true);
        }

        $this->getPage()->setVar(array(
            'SUMMARY_TODAY' => $today->getURLDate(),
            'SUMMARY_WEEK' => $today->addDays(7)->getURLDate(),
            'SUMMARY_DEPARTING' => $leavingToday,
            'SUMMARY_RETURNING' => $returningToday,
        ));

        $colourList = Timetable_ColourMapper::getInstance()->findAll(array("limit" => 5));

        $colourArray = array();
        if ($colourList->count() > 0) {
            foreach ($colourList as $i => $colour) {
                $colourArray[$i] = $colour->getHexColour();
            }
        } else {
            //if no timetable colours are available - use white, or we can use Primary BG colour (together with Body Text colour).
            $colourArray[0] = '#ffffff';
        }
        //Rotating available colours to set 5 different styles
        $k = 0;
        for ($i = 0; $i < 5; $i++) {
            if (!($colourArray[$k])) {
                $k = 0;
            }
            $this->getPage()->setVar(
                array(
                    'SUMMARY_COLOR_' . $i => $colourArray[$k],
                )
            );
            $k++;
        }
    }

    /**
     * Render host type dropdown
     *
     * @param \Leave_Request $request
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
     * Execute create request
     *
     * @return array
     */
    public function executeCreateRequest()
    {
        if (!$this->hasCreatePermission()) {
            error(Error\Mapper::ERROR_PERM_PAGE_VIEW);
        }

        return array();
    }

    /**
     * Renders a create request page
     *
     * @param array $data
     * @return void
     */
    public function renderCreateRequest($data)
    {
        $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/createRequest.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'student_selector', 'student_selector_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'travel_drop', 'travel_drop_block');
        $this->getPage()->setBlock('student_selector', 'student_drop', 'student_drop_block');
        $this->getPage()->setBlock(
            'custom_head',
            'student_autocomplete_javascript',
            'student_autocomplete_javascript_block'
        );
        $this->getPage()->setBlock(TEMPLATE_content, 'student_autocomplete', 'student_autocomplete_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'student_name', 'student_name_block');

        /* Render dropdowns
           */
        $request = new Leave_Request();

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
                'TERMS_AND_CONDITIONS' => Config::get('terms_and_conditions')
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
        $this->getPage()->setVar('student_name_block', '');

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
            $studentBoarders = array();
            foreach ($_SESSION['children'] as $child) {
                if ($this->getUserMapper()->findById($child['id'])->getResidentialHouse() instanceof HouseResidential) {
                    $studentBoarders[] = $child;
                }
            }
            $this->getPage()->dropDown(
                'student_drop',
                $studentBoarders,
                null,
                'student_selector',
                array(
                    'name' => 'STUDENT_DROP_NAME',
                    'id'   => 'STUDENT_DROP_VALUE',
                )
            );
            $this->getPage()->parse('student_selector_block', 'student_selector');
        } else {
            /* Student creating their own leave request
             */
            $this->getPage()->setVar([
                'STUDENT_NAME' => $this->getRequestMapper()->getSessionUser()->getFullName()
            ]);

            $this->getPage()->parse('student_name_block', 'student_name');
        }
    }

    /**
     * Does this user have create permission?
     *
     * The current user has permission if:
     * * They are staff, or
     * * They are a student in a Residential House,
     * * They are a parent of a student in a Residential House
     *
     * @return bool
     */
    protected function hasCreatePermission()
    {
        /** @var \Schoolbox\User $user */
        $user = $_SESSION["user"];

        // Are they staff?
        if ($user->getRole()->isStaff()) {
            return true;
        }

        // Are they a boarder?
        if ($user->getResidentialHouse() instanceof HouseResidential) {
            return true;
        }

        // Are they a parent of a boarder?
        if (is_array($_SESSION['children'])) {
            foreach ($_SESSION['children'] as $child) {
                if ($this->userMapper->findById($child['id'])->getResidentialHouse() instanceof HouseResidential) {
                    return true;
                }
            }
        }

        return false;
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
        /** @var \Schoolbox\User $user */
        $user = $_SESSION["user"];

        return $user->getId() == $userId
            || $this->getGroup()->getPermission($houseId)
            || $this->getGroup()->isParentOfChild($userId);
    }

    /**
     * The currently logged-in user has access to see reports
     *
     * @return mixed
     */
    protected function hasReportingPermission()
    {
        /** @var \Schoolbox\User $user */
        $user = $_SESSION["user"];

        return $user->getRole()->isStaff();
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
     * @param string $startDate
     * @return bool
     */
    private function isOutsideCreationThreshold($startDate)
    {
        $thresholdHours = \Config::get('boarder_leave_creation_threshold');

        /* Use 0 to deactivate this feature
         */
        if (!$thresholdHours) {
            return true;
        }

        $thresholdDateTime = \Alaress_Date_Format::newLocal($startDate);
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
        if ($_SERVER['REQUEST_TIME'] < $thresholdDateTime->getUnixTimeStamp()) {
            return true;
        }

        return false;
    }

    /**
     * Processes form data submitted from renderCreateRequest
     *
     * @param array $data
     * @throws ApplicationException\NotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    public function processCreateRequest($data)
    {
        if (!$this->hasCreatePermission()) {
            error(Error\Mapper::ERROR_PERM_PAGE_VIEW);
        }

        if (!is_array($data) || empty($data['request'])) {
            $this->getPage()->error(2);
        }

        /* Limit leave creation to a threshold
         */
        if ($this->isOutsideCreationThreshold($data['request']['start']['date'])) {
            $start = Alaress_Date_Format::newLocal($data['request']['start']['date']);
            $finish = Alaress_Date_Format::newLocal($data['request']['finish']['date']);
            $created = Alaress_Date_Format::now();
            $request = new Leave_Request();

            /** @var \Schoolbox\User $author */
            $author = $_SESSION["user"];

            /* Use the userId field when submitting on someone else's behalf,
             * otherwise just your own id
             */
            if (isset($data['userId'])) {
                try {
                    /** @var \Schoolbox\User $boarder */
                    $boarder = $this->getUserMapper()->findById($data['userId']);
                } catch (Schoolbox\Mapper\Exception $e) {
                    throw new ApplicationException\NotFound('User not found');
                }
            } else {
                $boarder = $author;
            }

            $request->setUser($boarder);
            $request->setAuthor($author);
            $request->setStart($start);
            $request->setFinish($finish);
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
     * @return array
     * @throws ApplicationException\NotFound if the request doesn't exist.
     */
    public function executeModifyRequest($requestId)
    {
        try {
            $request = $this->getRequestMapper()->findById($requestId);
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new ApplicationException\NotFound('Request not found');
        }
        return array('request' => $request);
    }

    /**
     * Renders a modify request form
     *
     * @param array $data
     * @return void
     * @throws ApplicationException\NotFound if the requester's house doesn't exist.
     */
    public function renderModifyRequest($data)
    {
        /** @var Leave_Request $request */
        $request = $data['request'];
        $user = $request->getUser();
        if (!$user instanceof \Schoolbox\User) {
            throw new ApplicationException\NotFound('User not found');
        }
        try {
            if ($user->getResidentialHouse() instanceof HouseResidential) {
                $houseId = $user->getResidentialHouse()->getId();
                $house = $this->getHouseMapper()->findById($houseId);
            } else {
                throw new ApplicationException\NotFound('User\'s house not found');
            }
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new ApplicationException\NotFound('User\'s house not found');
        }
        if (!$this->hasApprovePermission($house->getId())) {
            error(Error\Mapper::ERROR_PERM_PAGE_VIEW);
        }

        $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/modifyRequest.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'next_pending', 'next_pending_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'prev_pending', 'prev_pending_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'travel_drop', 'travel_drop_block');
        $this->getPage()->dropDown(
            'travel_drop',
            $this->travelTypes,
            $request->getTravelType() ? $request->getTravelType()->getId() : null,
            TEMPLATE_content,
            array(
                'name' => 'TRAVEL_DROP_NAME',
                'id'   => 'TRAVEL_DROP_VALUE',
            )
        );

        /* Render dropdowns
         */
        $this->renderHostTypeDropdown($request);
        $this->renderConfirmedByCheckboxes($request);

        $this->getPage()->setVar(array(
            'REQUEST_ID' => $request->getId(),
            'REQUEST_STATUS' => $request->getStatus(true),
            'AUTHOR_NAME' => $request->getAuthor()->formatName(),
            'REQUEST_USER_ID' => $user->getId(),
            'REQUEST_USER_NAME' => $user->formatName(),
            'REQUEST_USER_EXTERNAL_ID' => $user->getExternalId(),
            'REQUEST_USER_YEAR' => $user->getYear() ? $user->getYear()->getName() : '',
            'REQUEST_USER_HOUSE' => Scrub::text($house->getHouse()),
            'REQUEST_USER_HOUSE_ID' => $house->getId(),
            'REQUEST_START_DATE' => $request->getStart()->getLocal(),
            'REQUEST_FINISH_DATE' => $request->getFinish()->getLocal(),
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
            $request->getId(),
            $houseId
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
            $request->getId(),
            $houseId
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
     * @param array $data
     * @return void
     * @throws ApplicationException\NotFound if the user or their house doesn't exist.
     */
    public function renderViewRequest($data)
    {
        /* Allow the user and staff to view requests
         */

        /** @var Leave_Request $request */
        $request = $data['request'];
        $user = $request->getUser();
        if (!$user instanceof \Schoolbox\User) {
            throw new ApplicationException\NotFound('User not found');
        }
        try {
            if ($user->getResidentialHouse() instanceof HouseResidential) {
                $houseId = $user->getResidentialHouse()->getId();
                $house = $this->getHouseMapper()->findById($houseId);
            } else {
                throw new ApplicationException\NotFound('User\'s house not found');
            }
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new ApplicationException\NotFound('User\'s house not found');
        }

        if ($this->hasViewPermission($user->getId(), $house->getId())) {
            $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/viewRequest.html');

            $travelTypes = $this->getTravelTypes();

            if ($request->getTravelType() instanceof Leave_Request_TravelType) {
                $travelType = $travelTypes[$request->getTravelType()->getId()] ?: $travelTypes[0];
            } else {
                $travelType = $travelTypes[0];
            }

            $this->getPage()->setVar(
                array(
                    'REQUEST_ID' => $request->getId(),
                    'REQUEST_STATUS' => $request->getStatus(true),
                    'AUTHOR_NAME' => $request->getAuthor()->formatName(),
                    'USER_NAME' => $request->getUser()->formatName(),
                    'USER_EXTERNAL_ID' => $user->getExternalId(),
                    'USER_YEAR' => $user->getYear() ? $user->getYear()->getName() : '',
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
     * @param array $data
     * @return void
     * @throws ApplicationException\NotFound if request isn't found.
     */
    public function processModifyRequest($data)
    {
        /** @var Leave_Request $request */
        try {
            $request = $this->getRequestMapper()->findById($data['request']['id']);
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new ApplicationException\NotFound('Request not found');
        }

        $houseId = null;
        if ($request->getUser()->getResidentialHouse() instanceof HouseResidential) {
            $houseId = $request->getUser()->getResidentialHouse()->getId();
        }

        if (!$this->hasApprovePermission($houseId)) {
            error(Error\Mapper::ERROR_PERM_PAGE_VIEW);
        }

        $startTime = $data['request']['start']['time'] ?? '';
        $endTime = $data['request']['start']['time'] ?? '';
        $startString = trim("{$data['request']['start']['date']} {$startTime}");
        $endString   = trim("{$data['request']['finish']['date']} {$endTime}");

        $start = Alaress_Date_Format::newLocal($startString);
        $finish = Alaress_Date_Format::newLocal($endString);

        $request->setStart($start);
        $request->setFinish($finish);
        $request->setHostType($data['request']['hostType']);
        $request->setHostName($data['request']['hostName']);
        $request->setHostPhone($data['request']['hostPhone']);
        $request->setHostMobile($data['request']['hostMobile'] ?? '');
        $request->setHostAddress($data['request']['hostAddress'] ?? '');
        $request->setUserPhone($data['request']['userPhone']);
        $request->setDescription($data['request']['description']);
        $request->setNotes($data['request']['notes']);
        $request->setConfirmedBy($data['request']['confirmedBy']);
        $request->setReturnerName($data['request']['returnerName'] ?? '');
        $request->setCollectorName($data['request']['collectorName'] ?? '');
        $request->setReason($data['request']['reason'] ?? '');

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
     * @return array contains these keys:
     *               * house: the HouseResidential object
     *               * requestList: the requests for this house
     *
     * @throws ApplicationException\NotFound if the house $houseID doesn't exist.
     */
    public function executeHouseRequestList($houseId)
    {
        try {
            $house = $this->getHouseMapper()->findById($houseId);
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new ApplicationException\NotFound('House not found');
        }

        if (!$this->hasReportingPermission()) {
            error(Error\Mapper::ERROR_PERM_PAGE_VIEW);
        }

        $requestList = $this->getRequestMapper()->findAllByHouseId($house->getId());

        return array(
            'house' => $house,
            'requestList' => $requestList,
        );
    }

    /**
     * @param array $data
     */
    public function exportHouseRequestList($data)
    {
        /** @var \HouseResidential $house */
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

            /** @var Leave_Request $request */
            if ($request->getStatus() == Leave_Request::STATUS_APPROVED) {
                $rowData = array();

                $rowData[] = $request->getUser()->getId();
                $rowData[] = $request->getUser()->getFirstname();
                $rowData[] = $request->getUser()->getLastname();
                $rowData[] = $house->getHouse();
                $rowData[] = $request->getUser()->getYear() ? $request->getUser()->getYear()->getName() : '';
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
     * @param string $filename
     * @param array $allRows
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
     * @param array $data
     * @return void
     */
    public function renderHouseRequestList($data)
    {
        /** @var \HouseResidential $house */

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
                /** @var \Leave_Request $request */
                $this->getPage()->setVar(array(
                    'REQUEST_USER_ID' => $request->getUser()->getId(),
                    'REQUEST_USER_NAME' => $request->getUser()->formatName(),
                    'REQUEST_USER_YEAR' => $request->getUser()->getYear() ? $request->getUser()->getYear()->getName() : '',
                    'REQUEST_USER_BOARDING_STATUS' => $request->getUser()->getAbbreviatedBoardingStatus(),
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
     * @param int $userId
     * @return array
     * @throws ApplicationException\NotFound if user or their house isn't found.
     */
    public function executeUserRequestList($userId)
    {
        try {
            $user = $this->getUserMapper()->findById($userId);
            $house = $user->getResidentialHouse();
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new ApplicationException\NotFound('User not found');
        }
        try {
            if ($house instanceof HouseResidential) {
                $house = $this->getHouseMapper()->findById($house->getId());
            } else {
                throw new ApplicationException\NotFound('User not found');
            }
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new ApplicationException\NotFound('User not found');
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
     * @param array $data
     */
    public function exportUserRequestList($data)
    {
        /** @var Schoolbox\User $user */
        $user = $data['user'];
        $houseId = null;

        if ($user->getResidentialHouse() instanceof HouseResidential) {
            $houseId = $user->getResidentialHouse()->getId();
        }

        if ($this->hasViewPermission($user->getId(), $houseId)) {
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
                    $rowData[] = $request->getUser()->getYear() ? $request->getUser()->getYear()->getName() : '';
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
     * @param array $data
     * @throws ApplicationException\NotFound
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
            throw new ApplicationException\NotFound('User not found');
        }
        if (!$house instanceof HouseResidential) {
            throw new ApplicationException\NotFound('User\'s house not found');
        }
        if (!($this->hasViewPermission($user->getId(), $house->getId()))) {
            error(Error\Mapper::ERROR_PERM_PAGE_VIEW);
        }

        $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/userRequestList.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'no_requests', 'no_requests_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'pending_request_list', 'pending_request_list_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'previous_request_list', 'previous_request_list_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'reports_menu', 'reports_menu_block');
        $this->getPage()->setBlock('pending_request_list', 'pending_request', 'pending_request_block');
        $this->getPage()->setBlock('previous_request_list', 'previous_request', 'previous_request_block');
        $this->getPage()->setBlock('previous_request_list', 'export', 'export_block');

        if (!$this->hasReportingPermission()) {
            $this->getPage()->setVar('reports_menu_block', '');
        } else {
            $this->getPage()->parse('reports_menu_block', 'reports_menu', true);
        }

        if ($this->hasApprovePermission($house->getId())) {
            $this->getPage()->parse('export_block', 'export');
        } else {
            $this->getPage()->setVar('export_block', '');
        }

        $this->getPage()->setVar(
            array(
                'REPORT_USER_ID' => $user->getId(),
                'REPORT_USER_NAME' => $user->formatName(),
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
                /** @var \Leave_Request $request */
                $this->getPage()->setVar(
                    array(
                        'REQUEST_USER_ID' => $request->getUser()->getId(),
                        'REQUEST_USER_NAME' => $request->getUser()->formatName(),
                        'REQUEST_USER_YEAR' => $request->getUser()->getYear() ? $request->getUser()->getYear()->getName() : '',
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
//            error(Error\Mapper::ERROR_PERM_PAGE_VIEW);
//        }
    }

    /**
     * Processes modifications against a list of requests
     *
     * As far as I can tell, this is never used.
     *
     * @param array $data
     * @return void
     * @throws ApplicationException\NotFound if request isn't found
     */
    public function processModifyRequestList($data)
    {
        /* Only allow Teachers/Staff to approve/reject requests
         */
        try {
            $user = $this->getUserMapper()->findById($data['user']['id']);
            $house = $user->getResidentialHouse();
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new ApplicationException\NotFound('User not found');
        }

        if (!($house instanceof HouseResidential)) {
            throw new ApplicationException\NotFound('User\'s house not found');
        }

        if (!$this->hasApprovePermission($house->getId())) {
            error(Error\Mapper::ERROR_PERM_PAGE_VIEW);
        }

        $requestList = $data['requestList'];

        if (!empty($requestList)) {
            foreach ($requestList as $request) {
                /** @var Leave_Request $request */
                try {
                    $request = $this->getRequestMapper()->findById($request['id']);
                } catch (Schoolbox\Mapper\Exception $e) {
                    throw new ApplicationException\NotFound('Request not found');
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
     * @param \Leave_Request $request
     */
    private function renderConfirmedByCheckboxes(\Leave_Request $request)
    {
        $this->getPage()->setBlock(TEMPLATE_content, 'confirmed_by', 'confirmed_by_block');

        /* Iterate through options
         */
        $houseId = null;
        if ($request->getUser()->getResidentialHouse() instanceof HouseResidential) {
            $houseId = $request->getUser()->getResidentialHouse()->getId();
        }

        if ($this->hasApprovePermission($houseId)) {
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
     * @param int $houseId
     * @param string $timespan
     * @param string $dateStart
     * @param string $dateEnd
     * @throws ApplicationException\NotFound if house isn't found
     */
    public function executeHouseReport($houseId, $timespan, $dateStart, $dateEnd)
    {
        try {
            $house = $this->getHouseMapper()->findById($houseId);
        } catch (Schoolbox\Mapper\Exception $e) {
            throw new ApplicationException\NotFound('House not found');
        }

        if (!$this->hasReportingPermission()) {
            error(Error\Mapper::ERROR_PERM_PAGE_VIEW);
        }

        switch ($timespan) {
            case Leave_Controller::REPORT_TIMESPAN_DAY:
                $reportStart  = Alaress_Date_Format::newLocalShort($dateStart);
                $reportFinish = Alaress_Date_Format::newLocalShort($reportStart);
                break;
            case Leave_Controller::REPORT_TIMESPAN_RANGE:
                $reportStart  = Alaress_Date_Format::newLocalShort($dateStart);
                $reportFinish = Alaress_Date_Format::newLocalShort($dateEnd);
                break;
            case Leave_Controller::REPORT_TIMESPAN_WEEKEND:
            default:
                $reportStart  = Alaress_Date_Format::newLocalShort($dateStart);
                $reportStart->addDays(self::FRIDAY - $reportStart->getWeekDayNumber());
                $reportFinish = Alaress_Date_Format::newLocalShort($reportStart);
                $reportFinish->addDays(self::SUNDAY - self::FRIDAY);
                break;
        }

        $requestList = $this->getRequestMapper()->getHouseReport(
            $houseId,
            $reportStart->getDatabaseDate(),
            $reportFinish->getDatabaseDate()
        );

        $houseList = $this->getHouseMapper()->findAll();

        return array(
            'house'          => $house,
            'dateStart'      => $dateStart,
            'dateEnd'        => $dateEnd,
            'reportTimespan' => $timespan,
            'reportStart'    => $reportStart,
            'reportFinish'   => $reportFinish,
            'requestList'    => $requestList,
            'houseList'      => $houseList
        );
    }

    /**
     * Render a list of Leave Requests for people in a particular House.
     *
     * @param array $data
     */
    public function renderHouseReport($data)
    {
        /* Can wrap this in a factory to provide school-level customisation
           */
        $factory = new Leave_Report_House_Factory($this->getPage(), $data);
        $factory->produceWeb()->render();
    }

    /**
     * Export CSV of renderHouseReport()
     *
     * @param array $data
     */
    public function exportHouseReport($data)
    {
        /* Can wrap this in a factory to provide school-level customisation
           */
        $factory = new Leave_Report_House_Factory($this->getPage(), $data);
        $factory->produceCSV()->render();

        die();
    }

    /**
     * Export CSV of renderHouseReport()
     *
     * @param array $data
     */
    public function printHouseReport($data)
    {
        /* Can wrap this in a factory to provide school-level customisation
           */
        $factory = new Leave_Report_House_Factory($this->getPage(), $data);
        $factory->producePrint()->render();
    }

    /**
     * Get catering requirements for the Friday, Saturday and Sunday.
     * This is the period of time when people can go on leave.
     *
     * @param string $date
     * @return array
     */
    public function executeCateringReport($date)
    {
        if (!$this->hasReportingPermission()) {
            error(Error\Mapper::ERROR_PERM_PAGE_VIEW);
        }
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

            $allHouses = $this->getRequestMapper()->getCateringReport($currentDate);
            $data['houseList'][] = $allHouses;

            /* Summate totals for each house in this segment
                */
            $totals = array();

            $totals['present_morning'] = 0;
            $totals['present_day']     = 0;
            $totals['present_night']   = 0;
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
     * @param array $data
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
     * @param array $data
     */
    public function exportCateringReport($data)
    {
        $report = new Leave_Report_Catering_CSV($this->getPage(), $data);
        $report->render();
    }

    /**
     * Send email notification
     *
     * @param Leave_Request $request
     */
    public function sendUserNotice(Leave_Request $request)
    {
        /* Choose the Factory, have it produce() the notice object and send
         */
        $leaveFactory = new Leave_Notice_Factory($request);
        $notice       = $leaveFactory->produceUserFactory()->produce();
        if ($notice) {
            $notice->send();
        }
    }

    /**
     * Send a notice to a parent when someone creates a leave request
     *
     * @param Leave_Request $request
     */
    public function sendParentNotice(Leave_Request $request)
    {
        if (Config::get('boarder_leave_parent_notification_enabled')) {
            /* Choose the Factory, have it produce() the notice object and send
             */
            $leaveFactory = new Leave_Notice_Factory($request);
            $notice       = $leaveFactory->produceParentFactory()->produce();
            if ($notice) {
                $notice->send();
            }
        }
    }

    /**
     * Send a notice to a parent when someone creates a leave request
     *
     * At Kings Live, parent_notification started saving as integer 1/0, instead
     * of the usual true/false string?
     *
     * @param Leave_Request $request
     */
    public function sendAdminNotice(Leave_Request $request)
    {
        $leaveFactory = new Leave_Notice_Factory($request);
        $notice       = $leaveFactory->produceAdminFactory()->produce();
        if ($notice) {
            $notice->send();
        }
    }

    /**
     * Test email deployments
     */
    public function executeEmailTest()
    {
        $emailFactory = new EmailFactory(ConfigLoader::getInstance(), DKIMMapper::getInstance());
        $email = $emailFactory->buildTwigEmail('leave/userNoticeEmail.twig', 'test', 'steve.chan@alaress.com.au');

        $email->setFrom(Config::get('mail_from_address'), 'Test Schoolbox');
        $email->send();
    }

    /* Getters/Setters
      */

    /**
     * Set external user
     *
     * @param External_User $externalUser
     */
    public function setExternalUser($externalUser)
    {
        $this->externalUser = $externalUser;
    }

    /**
     * Get external user
     *
     * @return External_User
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
    private function setGroup(\Group $group)
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
     * @param HouseResidentialMapper $houseMapper
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
     * @return HouseResidentialMapper
     */
    public function getHouseMapper()
    {
        return $this->houseMapper;
    }

    /**
     * Set time map
     *
     * @param array $timeMap
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
    public function setHostTypes($hostTypes)
    {
        $this->hostTypes = $hostTypes;
        return $this;
    }

    /**
     * @return \Schoolbox\Collection|\Leave\HostType[]
     */
    public function getHostTypes()
    {
        return $this->hostTypes;
    }

    /**
     * Get the enum list of report periods available
     * @return array|string[]
     */
    public static function getTimespanOptions()
    {
        return self::$timespanOptions;
    }
}
