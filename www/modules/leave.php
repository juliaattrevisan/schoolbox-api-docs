<?php

class LeaveController {
    protected $page;

    protected $requestMapper;

    protected $userMapper;

    protected $houseMapper;

    /**
     * Setter for $requestMapper
     *
     * @param Leave_RequestMapper $requestMapper
     * @return void
     */
    protected function setRequestMapper ($requestMapper){
        $this->requestMapper = $requestMapper;
    }

    /**
     * Getter for $requestMapper
     *
     * @return Leave_RequestMapper
     */
    protected function getRequestMapper () {
        return $this->requestMapper;
    }

    /**
     * Setter for $page
     *
     * @param Page $page
     * @return void
     */
    public function setPage ($page) {
        $this->page = $page;
    }

    /**
     * Getter for $page
     *
     * @return SitePage
     */
    public function getPage () {
        return $this->page;
    }

    /**
     * Setter for $userMapper
     *
     * @param UserMapper $userMapper
     * @return void
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
    }

    /**
     * Getter for $userMapper
     *
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * Setter for $houseMapper
     *
     * @param HouseMapper $houseMapper
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
     * Constructor
     *
     * @param Page $page
     */
    public function __construct ($page) {
        $this->setPage($page);
        
        $this->setUserMapper( NewUserMapper::getInstance() );
        $this->setRequestMapper( Leave_RequestMapper::getInstance() );
        $this->setHouseMapper( HouseMapper::getInstance() );
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
        $this->getPage()->setVar($data);
    }

    /**
     * Processes form data submitted from renderCreateRequest
     *
     * @param Array $data
     * @return void
     */
    public function processCreateRequest ($data) {
        if ( !is_array($data) || empty($data['request']) ) {
            $this->getPage()->error(2);
        }

        $start = new Alaress_Date_Format("{$data['request']['start']['date']} {$data['request']['start']['hour']}:{$data['request']['start']['minute']} {$data['request']['start']['meridian']}", 'd/m/y');
        $finish = new Alaress_Date_Format("{$data['request']['finish']['date']} {$data['request']['finish']['hour']}:{$data['request']['finish']['minute']} {$data['request']['finish']['meridian']}", 'd/m/y');

        $created = new Alaress_Date_Format( time(), 'U');

        $request = new Leave_Request();

        $request->setUserId($_SESSION['id']);
        $request->setStart( $start->getDatabase() );
        $request->setFinish( $finish->getDatabase() );
        $request->setHostType($data['request']['hostType']);
        $request->setHostName($data['request']['hostName']);
        $request->setHostPhone($data['request']['hostPhone']);
        $request->setUserPhone($data['request']['userPhone']);
        $request->setDescription($data['request']['description']);
        $request->setStatus(Leave_Request::STATUS_PENDING);
        $request->setCreated( $created->getDatabase() );

        $this->getRequestMapper()->save($request);

        $this->getPage()->backToTheFuture(-1);
    }

    /**
     * Returns a collection of requests by house id
     *
     * @param Integer $houseId
     * @return Array
     */
    public function executeHouseRequestList ($houseId) {
        $house = $this->getHouseMapper()->findById($houseId);
        $requestList = $this->getRequestMapper()->findAllByHouseId( $house->getId() );

        return array(
            'house'       => $house,
            'requestList' => $requestList,
        );
    }

    /**
     * Renders a collection of requests by house
     *
     * @param Array $data
     * @return void
     */
    public function renderHouseRequestList ($data) {
        $house = $data['house'];
        $requestList = $data['requestList'];

        $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/houseRequestList.html');

        $this->getPage()->setBlock(TEMPLATE_content, 'no_requests', 'no_requests_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'pending_request_list', 'pending_request_list_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'previous_request_list', 'previous_request_list_block');
        $this->getPage()->setBlock('pending_request_list', 'pending_request', 'pending_request_block');
        $this->getPage()->setBlock('previous_request_list', 'previous_request', 'previous_request_block');

        $this->getPage()->setVar( array(
            'HOUSE_ID' => $house->getId(),
            'HOUSE_NAME' => $house->getHouse()
        ) );

        if ( count($requestList) ) {
            $this->getPage()->setVar('no_requests_block', '');

            $pendingRequestCount = 0;
            $previousRequestCount = 0;

            foreach ( $requestList as $request ) {
                $this->getPage()->setVar( array(
                    'USER_ID' => $request->getUser()->getId(),
                    'USER_NAME' => $request->getUser()->getName(),
                    'USER_YEAR' => $request->getUser()->getYear(),
                    'REQUEST_ID' => $request->getId(),
                    'REQUEST_START' => $request->getStart(true)->getAustralian(),
                    'REQUEST_DURATION' => $request->getDuration(),
                    'REQUEST_HOST_TYPE' => $request->getHostType(true)
                ) );

                if ( $request->getStatus() == Leave_Request::STATUS_PENDING ) {
                    $this->getPage()->setVar( array(
                        'CLASS' => $pendingRequestCount % 2 ? '' : 'dark',
                    ) );

                    $this->getPage()->parse('pending_request_block', 'pending_request', true);

                    $pendingRequestCount++;
                }
                else {
                    $this->getPage()->setVar( array(
                        'REQUEST_STATUS' => $request->getStatus(true),
                        'REQUEST_AUTHORISATION_DATETIME' => $request->getAuthorisationDatetime(true)->getAustralian(),
                        'REQUEST_AUTHORISATION_USER_ID' => $request->getAuthorisationUser()->getId(),
                        'REQUEST_AUTHORISATION_USER_NAME' => $request->getAuthorisationUser()->getName(),
                        'CLASS' => $previousRequestCount % 2 ? '' : 'dark',
                    ) );

                    $this->getPage()->parse('previous_request_block', 'previous_request', true);

                    $previousRequestCount++;
                }
            }

            if ( $pendingRequestCount ) {
                $this->getPage()->parse('pending_request_list_block', 'pending_request_list');
            }
            else {
                $this->getPage()->setVar('pending_request_list_block', '');
            }

            if ( $previousRequestCount ) {
                $this->getPage()->parse('previous_request_list_block', 'previous_request_list');
            }
            else {
                $this->getPage()->setVar('previous_request_list_block', '');
            }
        }
        else {
            $this->getPage()->parse('no_requests_block', 'no_requests');
        }
    }

    /**
     * Returns a collection of requests for a user
     *
     * @param $userId
     * @return Collection
     */
    public function executeUserRequestList ($userId) {
        $user = $this->getUserMapper()->findById($userId);
        $house = $this->getHouseMapper()->findById( $user->getHouse() );
        $requestList = $this->getRequestMapper()->findAllByUserId( $user->getId() );

        return array(
            'user'        => $user,
            'house'       => $house,
            'requestList' => $requestList,
        );
    }

    /**
     * Renders a collection of request for a user
     *
     * @param $data
     * @return void
     */
    public function renderUserRequestList ($data) {
        $user = $data['user'];
        $house = $data['house'];
        $requestList = $data['requestList'];

        $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/userRequestList.html');
        
        $this->getPage()->setBlock(TEMPLATE_content, 'no_requests', 'no_requests_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'pending_request_list', 'pending_request_list_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'previous_request_list', 'previous_request_list_block');
        $this->getPage()->setBlock('pending_request_list', 'pending_request', 'pending_request_block');
        $this->getPage()->setBlock('previous_request_list', 'previous_request', 'previous_request_block');

        $this->getPage()->setVar( array(
            'USER_ID' => $user->getId(),
            'USER_NAME' => $user->getName(),
            'HOUSE_NAME' => $house->getHouse(),
            'HOUSE_ID' => $house->getId()
        ) );

        if ( count($requestList) ) {
            $this->getPage()->setVar('no_requests_block', '');

            $pendingRequestCount = 0;
            $previousRequestCount = 0;

            foreach ( $requestList as $request ) {
                $this->getPage()->setVar( array(
                    'USER_ID' => $request->getUser()->getId(),
                    'USER_NAME' => $request->getUser()->getName(),
                    'USER_YEAR' => $request->getUser()->getYear(),
                    'REQUEST_ID' => $request->getId(),
                    'REQUEST_START' => $request->getStart(true)->getAustralian(),
                    'REQUEST_DURATION' => $request->getDuration(),
                    'REQUEST_HOST_TYPE' => $request->getHostType(true)
                ) );

                if ( $request->getStatus() == Leave_Request::STATUS_PENDING ) {
                    $this->getPage()->setVar( array(
                        'CLASS' => $pendingRequestCount % 2 ? '' : 'dark',
                    ) );

                    $this->getPage()->parse('pending_request_block', 'pending_request', true);

                    $pendingRequestCount++;
                }
                else {
                    $this->getPage()->setVar( array(
                        'REQUEST_STATUS' => $request->getStatus(true),
                        'REQUEST_AUTHORISATION_DATETIME' => $request->getAuthorisationDatetime(true)->getAustralian(),
                        'REQUEST_AUTHORISATION_USER_ID' => $request->getAuthorisationUser()->getId(),
                        'REQUEST_AUTHORISATION_USER_NAME' => $request->getAuthorisationUser()->getName(),
                        'CLASS' => $previousRequestCount % 2 ? '' : 'dark',
                    ) );

                    $this->getPage()->parse('previous_request_block', 'previous_request', true);

                    $previousRequestCount++;
                }
            }

            if ( $pendingRequestCount ) {
                $this->getPage()->parse('pending_request_list_block', 'pending_request_list');
            }
            else {
                $this->getPage()->setVar('pending_request_list_block', '');
            }

            if ( $previousRequestCount ) {
                $this->getPage()->parse('previous_request_list_block', 'previous_request_list');
            }
            else {
                $this->getPage()->setVar('previous_request_list_block', '');
            }
        }
        else {
            $this->getPage()->parse('no_requests_block', 'no_requests');
        }
    }

    /**
     * Processes modifications against a list of requests
     *
     * @param Array $data
     * @return void
     */
    public function processModifyRequestList ($data) {
        $user = $data['user'];
        $requestList = $data['requestList'];

        $requestCollection = new Collection;

        if ( !empty($requestList) ) {
            foreach ( $requestList as $request ) {
                $request = $this->getRequestMapper()->findById($request['id']);

                if ( isset($data['approve']) ) {
                    $request->setStatus(Leave_Request::STATUS_APPROVED);
                }
                elseif ( isset($data['reject']) ) {
                    $request->setStatus(Leave_Request::STATUS_REJECTED);
                }

                $authorisationDatetime = new Alaress_Date_Format( time(), 'U');

                $request->setAuthorisationDatetime( $authorisationDatetime->getDatabase() );

                $request->setAuthorisationUserId($_SESSION['id']);

                $requestCollection[] = $request;
            }
        }

        $this->getRequestMapper()->save($requestCollection);

        $this->getPage()->localRedir("/modules/leave/student/{$user['id']}");
    }

    /**
     * Returns a data array with the request to be modified
     *
     * @param Integer $requestId
     * @return Array
     */
    public function executeModifyRequest ($requestId) {
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
    public function renderModifyRequest ($data) {
        $request = $data['request'];
        $user = $request->getUser();
        $house = $this->getHouseMapper()->findById( $user->getHouse() );

        $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/modifyRequest.html');

        $this->getPage()->setVar( array(
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
        ) );

        if ( $request->getStart(true)->getUsingFormat('a') == 'am' ) {
            $this->getPage()->setVar('REQUEST_START_MERIDIAN_AM', 'selected="selected"');
        }
        else {
            $this->getPage()->setVar('REQUEST_START_MERIDIAN_PM', 'selected="selected"');
        }

        if ( $request->getFinish(true)->getUsingFormat('a') == 'am' ) {
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
    public function processModifyRequest ($data) {
        $request = $this->getRequestMapper()->findById( $data['request']['id'] );

        $start = new Alaress_Date_Format("{$data['request']['start']['date']} {$data['request']['start']['hour']}:{$data['request']['start']['minute']} {$data['request']['start']['meridian']}", 'd/m/y');
        $finish = new Alaress_Date_Format("{$data['request']['finish']['date']} {$data['request']['finish']['hour']}:{$data['request']['finish']['minute']} {$data['request']['finish']['meridian']}", 'd/m/y');

        $request->setStart( $start->getDatabase() );
        $request->setFinish( $finish->getDatabase() );
        $request->setHostType($data['request']['hostType']);
        $request->setHostName($data['request']['hostName']);
        $request->setHostPhone($data['request']['hostPhone']);
        $request->setUserPhone($data['request']['userPhone']);
        $request->setDescription($data['request']['description']);

        if ( isset($data['approve']) ) {
            $authorisationDatetime = new Alaress_Date_Format( time(), 'U');

            $request->setAuthorisationDatetime( $authorisationDatetime->getDatabase() );
            $request->setAuthorisationUserId($_SESSION['id']);
            $request->setStatus(Leave_Request::STATUS_APPROVED);
        }
        elseif ( isset($data['reject']) ) {
            $authorisationDatetime = new Alaress_Date_Format( time(), 'U');

            $request->setAuthorisationDatetime( $authorisationDatetime->getDatabase() );
            $request->setAuthorisationUserId($_SESSION['id']);
            $request->setStatus(Leave_Request::STATUS_REJECTED);
        }

        $this->getRequestMapper()->save($request);

        $this->getPage()->backToTheFuture(2);
    }

    public function executeHouseReport ($houseId) {
        $house = $this->getHouseMapper()->findById($houseId);

        $reportStart = new Alaress_Date_Format(time(), 'U');
        $reportStart->addDays( 6 - $reportStart->getWeekDayNumber() );

        $reportFinish = new Alaress_Date_Format(time(), 'U');
        $reportFinish->addDays( 7 - $reportFinish->getWeekDayNumber() );

        $requestList = $this->getRequestMapper()->findUserListByHouseId($houseId);

        return array(
            'house'        => $house,
            'reportStart'  => $reportStart,
            'reportFinish' => $reportFinish,
            'requestList'  => $requestList,
        );
    }

    public function renderHouseReport ($data) {
        $house = $data['house'];
        $requestList = $data['requestList'];

        $reportStart = new Alaress_Date_Format( $data['reportStart']->getDatabaseDate() , 'y-m-d' );

        $reportStartMorning = new Alaress_Date_Format( $data['reportStart']->getDatabaseDate() , 'y-m-d' );
        $reportStartMorning->addDays(1/3);

        $reportStartDay = new Alaress_Date_Format( $data['reportStart']->getDatabaseDate() , 'y-m-d' );
        $reportStartDay->addDays(2/3);

        $reportStartNight = new Alaress_Date_Format( $data['reportStart']->getDatabaseDate() , 'y-m-d' );
        $reportStartNight->addDays(3/3);

        $reportFinish = new Alaress_Date_Format( $data['reportFinish']->getDatabaseDate() , 'y-m-d' );

        $reportFinishMorning = new Alaress_Date_Format( $data['reportFinish']->getDatabaseDate() , 'y-m-d' );
        $reportFinishMorning->addDays(1/3);

        $reportFinishDay = new Alaress_Date_Format( $data['reportFinish']->getDatabaseDate() , 'y-m-d' );
        $reportFinishDay->addDays(2/3);

        $reportFinishNight = new Alaress_Date_Format( $data['reportFinish']->getDatabaseDate() , 'y-m-d' );
        $reportFinishNight->addDays(3/3);        
        
        $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/houseReport.html');

        $this->getPage()->setBlock(TEMPLATE_content, 'no_requests', 'no_requests_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'row', 'row_block');
        $this->getPage()->setBlock('row', 'request', 'request_block');
        $this->getPage()->setBlock('row', 'empty_request', 'empty_request_block');

        $this->getPage()->setVar( array(
            'HOUSE_NAME' => $house->getHouse(),
            'HOUSE_ID' => $house->getId(),
            'REPORT_START_DATE' => $reportStart->getLong(),
            'REPORT_FINISH_DATE' => $reportFinish->getLong(),
        ) );

        if ( count($requestList) ) {
            $this->getPage()->setVar('no_requests_block', '');

            foreach ( $requestList as $i => $request ) {
                $this->getPage()->setVar( array(
                    'USER_ID' => $request->getUser()->getId(),
                    'USER_NAME' => $request->getUser()->getName(),
                    'USER_YEAR' => $request->getUser()->getYear(),
                    'CLASS' => $i % 2 ? '' : 'dark'
                ) );

                if ( $request->getId() ) {
                    $this->getPage()->setVar( array(
                        'REQUEST_ID' => $request->getId(),
                        'REQUEST_STATUS' => $request->getStatus(true),
                        'REQUEST_START_MORNING' => $request->getOccupancy($reportStart, $reportStartMorning) ? 'L' : '',
                        'REQUEST_START_DAY' => $request->getOccupancy($reportStartMorning, $reportStartDay) ? 'L' : '',
                        'REQUEST_START_NIGHT' => $request->getOccupancy($reportStartDay, $reportStartNight) ? 'L' : '',
                        'REQUEST_FINISH_MORNING' => $request->getOccupancy($reportFinish, $reportFinishMorning) ? 'L' : '',
                        'REQUEST_FINISH_DAY' => $request->getOccupancy($reportFinishMorning, $reportFinishDay) ? 'L' : '',
                        'REQUEST_FINISH_NIGHT' => $request->getOccupancy($reportFinishDay, $reportFinishNight) ? 'L' : '',
                        'REQUEST_HOST_TYPE' => $request->getHostType(true),
                    ) );

                    if ( $request->getStatus() != Leave_Request::STATUS_PENDING ) {
                        $this->getPage()->setVar( array(
                            'REQUEST_STATUS' => $request->getStatus(true),
                            'REQUEST_AUTHORISATION_DATETIME' => $request->getAuthorisationDatetime(true)->getAustralian(),
                            'REQUEST_AUTHORISATION_USER_ID' => $request->getAuthorisationUser()->getId(),
                            'REQUEST_AUTHORISATION_USER_NAME' => $request->getAuthorisationUser()->getName(),
                        ) );
                    }
                    else {
                        $this->getPage()->setVar( array(
                            'REQUEST_STATUS' => $request->getStatus(true),
                            'REQUEST_AUTHORISATION_DATETIME' => 'Pending Approval',
                            'REQUEST_AUTHORISATION_USER_ID' => '',
                            'REQUEST_AUTHORISATION_USER_NAME' => '',
                        ) );
                    }

                    $this->getPage()->setVar('empty_request_block', '');
                    $this->getPage()->parse('request_block', 'request');
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
        }
    }
}

/* Routing */
$leaveController = new LeaveController($this);

$method = $this->arg[0];

if ( $method == 'create' ) {
    $data = $leaveController->executeCreateRequest();
    $leaveController->renderCreateRequest($data);
}
elseif ( $method == 'modify' && is_numeric($this->arg[1]) ) {
    $data = $leaveController->executeModifyRequest($this->arg[1]);
    $leaveController->renderModifyRequest($data);
}
elseif ( $method == 'student' && is_numeric($this->arg[1]) ) {
    $data = $leaveController->executeUserRequestList($this->arg[1]);
    $leaveController->renderUserRequestList($data);
}
elseif ( $method == 'house' ) {
    if ( is_numeric($this->arg[1]) ) {
        $houseId = $this->arg[1];
    }
    else {
        $houseId = $_SESSION['houseId'];
    }

    $data = $leaveController->executeHouseRequestList($houseId);
    $leaveController->renderHouseRequestList($data);
}
elseif ( $method == 'report' && $this->arg[1] == 'house' ) {
    if ( is_numeric($this->arg[2]) ) {
        $houseId = $this->arg[2];
    }
    else {
        $houseId = $_SESSION['houseId'];
    }

    $data = $leaveController->executeHouseReport($houseId);
    $leaveController->renderHouseReport($data);
}
elseif ( $method == 'processCreate' ) {
    $leaveController->processCreateRequest($_POST);
}
elseif ( $method == 'processModify' ) {
    $leaveController->processModifyRequest($_POST);
}
elseif ( $method == 'processModifyList' ) {
    $leaveController->processModifyRequestList($_POST);
}
else {
    $data = $leaveController->executeHouseRequestList($_SESSION['houseId']);
    $leaveController->renderHouseRequestList($data);
}