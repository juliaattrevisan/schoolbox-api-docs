<?php

/* Routing */
$leaveController = new Leave_Controller($this);

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

    if ( is_string($this->arg[3]) ) {
        $date = str_replace('-', '/', $this->arg[3]);
    }
    else {
        $date = date('d/m/Y', time());
    }

    $data = $leaveController->executeHouseReport($houseId, $date);

    $argumentCount = count($this->arg);

    if ( $this->arg[$argumentCount - 1] == 'export') {
        $leaveController->exportHouseReport($data);
    }
    else {
        $leaveController->renderHouseReport($data);
    }
}
elseif ( $method == 'report' && $this->arg[1] == 'catering' ) {
    if ( is_string($this->arg[2]) ) {
        $date = str_replace('-', '/', $this->arg[2]);
    }
    else {
        $date = date('d/m/Y', time());
    }

    $data = $leaveController->executeCateringReport($date);

    $argumentCount = count($this->arg);

    if ( $this->arg[$argumentCount - 1] == 'export') {
        $leaveController->exportCateringReport($data);
    }
    else {
        $leaveController->renderCateringReport($data);
    }

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