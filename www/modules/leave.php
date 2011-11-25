<?php

class LeaveController {
    protected $page;

    protected $requestMapper;

    protected $userHandler;

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
     * Setter for $userHandler
     *
     * @param User $userHandler
     * @return void
     */
    public function setUserHandler($userHandler)
    {
        $this->userHandler = $userHandler;
    }

    /**
     * Getter for $userHandler
     *
     * @return User
     */
    public function getUserHandler()
    {
        return $this->userHandler;
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

    public function __construct ($page) {
        $this->setPage($page);
        $this->setUserHandler( new User );

        $this->setRequestMapper( Leave_RequestMapper::getInstance() );
        $this->setHouseMapper( HouseMapper::getInstance() );
    }

    public function executeCreateRequest () {
        return array();
    }

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

    public function executeHouseRequestList ($houseId) {
        $house = $this->getHouseMapper()->findById($houseId);

        $data = array(
            'house' => $house,
            'pendingRequestList' => new Collection,
            'previousRequestList' => new Collection
        );

        $requestList = $this->getRequestMapper()->findByHouseId( $house->getId() );

        if ( !empty($requestList) ) {
            foreach ( $requestList as $request ) {
                if ( $request->getStatus() == Leave_Request::STATUS_PENDING ) {
                    $data['pendingRequestList'][] = $request;
                }
                else {
                    $data['previousRequestList'][] = $request;
                }
            }
        }

        return $data;
    }

    public function renderHouseRequestList ($data) {
        $house = $data['house'];
        $pendingRequestList = $data['pendingRequestList'];
        $previousRequestList = $data['previousRequestList'];

        $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/houseRequestList.html');

        $this->getPage()->setBlock(TEMPLATE_content, 'no_requests', 'no_requests_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'pending_request', 'pending_request_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'previous_request', 'previous_request_block');

        $this->getPage()->setVar( array(
            'HOUSE_NAME' => $house->getHouse()
        ) );

        if ( is_a($pendingRequestList, 'Collection') && count($pendingRequestList) ) {
            foreach ( $pendingRequestList as $pendingRequest ) {
                $user = $pendingRequest->getUser();

                $start = new Alaress_Date_Format( $pendingRequest->getStart(), 'y-m-d' );
                $finish = new Alaress_Date_Format( $pendingRequest->getFinish(), 'y-m-d' );

                $duration = floor( ( $finish->getUnixTimestamp() - $start->getUnixTimestamp() ) / ( 60 * 60 * 24) );

                $this->getPage()->setVar( array(
                    'USER_ID' => $user['id'],
                    'USER_NAME' => $this->getUserHandler()->formatName($user),
                    'USER_YEAR' => $user['year'],
                    'REQUEST_ID' => $pendingRequest->getId(),
                    'REQUEST_START' => $start->getAustralian(),
                    'REQUEST_DURATION' => $duration,
                    'REQUEST_HOST_TYPE' => $pendingRequest->getHostType(true)
                ) );

                $this->getPage()->parse('pending_request_block', 'pending_request', true);
            }
        }
        else {
            $this->getPage()->setBlock(TEMPLATE_content, 'pending_request_list', 'pending_request_list_block');
            $this->getPage()->setVar('pending_request_list_block', '');
        }

        if ( is_a($previousRequestList, 'Collection') && count($previousRequestList) ) {
            foreach ( $previousRequestList as $previousRequest ) {
                $user = $previousRequest->getUser();
                $authorisationUser = $previousRequest->getAuthorisationUser();

                $start = new Alaress_Date_Format( $previousRequest->getStart(), 'y-m-d' );
                $finish = new Alaress_Date_Format( $previousRequest->getFinish(), 'y-m-d' );

                $duration = floor( ( $finish->getUnixTimestamp() - $start->getUnixTimestamp() ) / ( 60 * 60 * 24) );

                $authorisationDatetime = new Alaress_Date_Format( $previousRequest->getAuthorisationDatetime(), 'y-m-d' );

                $this->getPage()->setVar( array(
                    'USER_ID' => $user['id'],
                    'USER_NAME' => $this->getUserHandler()->formatName($user),
                    'USER_YEAR' => $user['year'],
                    'REQUEST_ID' => $previousRequest->getId(),
                    'REQUEST_START' => $start->getAustralian(),
                    'REQUEST_DURATION' => $duration,
                    'REQUEST_HOST_TYPE' => $previousRequest->getHostType(true),
                    'REQUEST_STATUS' => $previousRequest->getStatus(true),
                    'REQUEST_AUTHORISATION_DATETIME' => $authorisationDatetime->getAustralian(),
                    'REQUEST_AUTHORISATION_USER_ID' => $authorisationUser['id'],
                    'REQUEST_AUTHORISATION_USER_NAME' => $this->getUserHandler()->formatName($authorisationUser),
                ) );

                $this->getPage()->parse('previous_request_block', 'previous_request', true);
            }
        }
        else {
            $this->getPage()->setBlock(TEMPLATE_content, 'previous_request_list', 'previous_request_list_block');
            $this->getPage()->setVar('previous_request_list_block', '');
        }

        if ( ( is_a($pendingRequestList, 'Collection') && count($pendingRequestList) ) || ( is_a($previousRequestList, 'Collection') && count($previousRequestList) ) ) {
            $this->getPage()->setBlock(TEMPLATE_content, 'no_requests', 'no_requests_block');
            $this->getPage()->setVar('no_requests_block', '');
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
        $user = $this->getUserHandler()->getByID($userId);
        $house = $this->getHouseMapper()->findById($user['house']);

        $data = array(
            'user' => $this->getUserHandler()->getByID($userId),
            'house' => $house,
            'pendingRequestList' => new Collection,
            'previousRequestList' => new Collection
        );

        $requestList = $this->getRequestMapper()->findByUserId($userId);

        if ( !empty($requestList) ) {
            foreach ( $requestList as $request ) {
                if ( $request->getStatus() == Leave_Request::STATUS_PENDING ) {
                    $data['pendingRequestList'][] = $request;
                }
                else {
                    $data['previousRequestList'][] = $request;
                }
            }
        }

        return $data;
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
        
        $pendingRequestList = $data['pendingRequestList'];
        $previousRequestList = $data['previousRequestList'];

        $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/userRequestList.html');
        
        $this->getPage()->setBlock(TEMPLATE_content, 'no_requests', 'no_requests_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'pending_request', 'pending_request_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'previous_request', 'previous_request_block');

        $this->getPage()->setVar( array(
            'USER_ID' => $user['uid'],
            'USER_NAME' => $this->getUserHandler()->formatName($user),
            'HOUSE_NAME' => $house->getHouse(),
            'HOUSE_ID' => $house->getId()
        ) );

        if ( is_a($pendingRequestList, 'Collection') && count($pendingRequestList) ) {
            foreach ( $pendingRequestList as $pendingRequest ) {
                $start = new Alaress_Date_Format( $pendingRequest->getStart(), 'y-m-d' );
                $finish = new Alaress_Date_Format( $pendingRequest->getFinish(), 'y-m-d' );

                $duration = floor( ( $finish->getUnixTimestamp() - $start->getUnixTimestamp() ) / ( 60 * 60 * 24) );

                $this->getPage()->setVar( array(
                    'USER_ID' => $user['uid'],
                    'USER_NAME' => $this->getUserHandler()->formatName($user),
                    'USER_YEAR' => $user['year'],
                    'REQUEST_ID' => $pendingRequest->getId(),
                    'REQUEST_START' => $start->getAustralian(),
                    'REQUEST_DURATION' => $duration,
                    'REQUEST_HOST_TYPE' => $pendingRequest->getHostType(true)
                ) );

                $this->getPage()->parse('pending_request_block', 'pending_request', true);
            }
        }
        else {
            $this->getPage()->setBlock(TEMPLATE_content, 'pending_request_list', 'pending_request_list_block');
            $this->getPage()->setVar('pending_request_list_block', '');
        }

        if ( is_a($previousRequestList, 'Collection') && count($previousRequestList) ) {
            foreach ( $previousRequestList as $previousRequest ) {
                $authorisationUser = $previousRequest->getAuthorisationUser();

                $start = new Alaress_Date_Format( $previousRequest->getStart(), 'y-m-d' );
                $finish = new Alaress_Date_Format( $previousRequest->getFinish(), 'y-m-d' );

                $duration = floor( ( $finish->getUnixTimestamp() - $start->getUnixTimestamp() ) / ( 60 * 60 * 24) );

                $authorisationDatetime = new Alaress_Date_Format( $previousRequest->getAuthorisationDatetime(), 'y-m-d' );

                $this->getPage()->setVar( array(
                    'USER_ID' => $user['uid'],
                    'USER_NAME' => $this->getUserHandler()->formatName($user),
                    'USER_YEAR' => $user['year'],
                    'REQUEST_ID' => $previousRequest->getId(),
                    'REQUEST_START' => $start->getAustralian(),
                    'REQUEST_DURATION' => $duration,
                    'REQUEST_HOST_TYPE' => $previousRequest->getHostType(true),
                    'REQUEST_STATUS' => $previousRequest->getStatus(true),
                    'REQUEST_AUTHORISATION_DATETIME' => $authorisationDatetime->getAustralian(),
                    'REQUEST_AUTHORISATION_USER_ID' => $authorisationUser['id'],
                    'REQUEST_AUTHORISATION_USER_NAME' => $this->getUserHandler()->formatName($authorisationUser),
                ) );

                $this->getPage()->parse('previous_request_block', 'previous_request', true);
            }
        }
        else {
            $this->getPage()->setBlock(TEMPLATE_content, 'previous_request_list', 'previous_request_list_block');
            $this->getPage()->setVar('previous_request_list_block', '');
        }

        if ( ( is_a($pendingRequestList, 'Collection') && count($pendingRequestList) ) || ( is_a($previousRequestList, 'Collection') && count($previousRequestList) ) ) {
            $this->getPage()->setBlock(TEMPLATE_content, 'no_requests', 'no_requests_block');
            $this->getPage()->setVar('no_requests_block', '');
        }
        else {
            $this->getPage()->parse('no_requests_block', 'no_requests');
        }
    }

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

    public function executeModifyRequest ($requestId) {
        $data = array(
            'request' => $this->getRequestMapper()->findById($requestId)
        );

        return $data;
    }

    public function renderModifyRequest ($data) {
        $request = $data['request'];

        $user = $request->getUser();

        $house = $this->getHouseMapper()->findById($user['house']);

        $start = new Alaress_Date_Format($request->getStart(), 'y-m-d');
        $finish = new Alaress_Date_Format($request->getFinish(), 'y-m-d');

        $this->getPage()->setFile(TEMPLATE_content, 'modules/leave/modifyRequest.html');

        $this->getPage()->setVar( array(
            'REQUEST_ID' => $request->getId(),
            'REQUEST_STATUS' => $request->getStatus(true),
            'USER_NAME' => $this->getUserHandler()->formatName($user),
            'USER_EXTERNAL_ID' => $user['synergy_id'],
            'USER_YEAR' => $user['year'],
            'USER_HOUSE' => $house->getHouse(),
            'REQUEST_START_DATE' => $start->getLocalShort(),
            'REQUEST_START_HOUR' => $start->getUsingFormat('h'),
            'REQUEST_START_MINUTE' => $start->getUsingFormat('i'),
            'REQUEST_FINISH_DATE' => $finish->getLocalShort(),
            'REQUEST_FINISH_HOUR' => $finish->getUsingFormat('h'),
            'REQUEST_FINISH_MINUTE' => $finish->getUsingFormat('i'),
            'REQUEST_HOST_NAME' => $request->getHostName(),
            'REQUEST_HOST_PHONE' => $request->getHostPhone(),
            'REQUEST_USER_PHONE' => $request->getUserPhone(),
            'REQUEST_DESCRIPTION' => $request->getDescription(),
        ) );

        if ( $start->getUsingFormat('a') == 'am' ) {
            $this->getPage()->setVar('REQUEST_START_MERIDIAN_AM', 'selected="selected"');
        }
        else {
            $this->getPage()->setVar('REQUEST_START_MERIDIAN_PM', 'selected="selected"');
        }

        if ( $finish->getUsingFormat('a') == 'am' ) {
            $this->getPage()->setVar('REQUEST_FINISH_MERIDIAN_AM', 'selected="selected"');
        }
        else {
            $this->getPage()->setVar('REQUEST_FINISH_MERIDIAN_PM', 'selected="selected"');
        }
    }

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
}

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