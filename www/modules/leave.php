<?php

/* Routing */
$leaveController = new Leave_Controller($this);

$method = $this->arg[0];

try {
    if ($method == 'create') {
        $data = $leaveController->executeCreateRequest();
        $leaveController->renderCreateRequest($data);
    } else if ($method == 'modify' && is_numeric($this->arg[1])) {
        $data = $leaveController->executeModifyRequest($this->arg[1]);
        $leaveController->renderModifyRequest($data);
    } else if ($method == 'view' && is_numeric($this->arg[1])) {
        $data = $leaveController->executeModifyRequest($this->arg[1]);
        $leaveController->renderViewRequest($data);
    } else if ($method == 'student' && is_numeric($this->arg[1])) {
        $data = $leaveController->executeUserRequestList($this->arg[1]);
        $leaveController->renderUserRequestList($data);
    } /* Export a csv history of a particular student's leave requests
     */
    else if (($method == 'student') && ($this->arg[1] == 'export') && is_numeric($this->arg[2])) {
        $data = $leaveController->executeUserRequestList($this->arg[2]);

        $leaveController->exportUserRequestList($data);
    } /* Export a csv history of a particular house's leave requests
     */
    else if (($method == 'house') && ($this->arg[1] == 'export') && is_numeric($this->arg[2])) {
        $data = $leaveController->executeHouseRequestList($this->arg[2]);

        $leaveController->exportHouseRequestList($data);
    } else {
        if ($method == 'house') {
            if (is_numeric($this->arg[1])) {
                $houseId = $this->arg[1];
            } else {
                $houseId = $_SESSION['houseId'];
            }

            $data = $leaveController->executeHouseRequestList($houseId);
            $leaveController->renderHouseRequestList($data);
        } else {
            if ($method == 'report' && $this->arg[1] == 'house') {
                if (is_numeric($this->arg[2])) {
                    $houseId = $this->arg[2];
                } else {
                    $houseId = $_SESSION['houseId'];
                }

                if (is_string($this->arg[3])) {
                    $date = str_replace('-', '/', $this->arg[3]);
                } else {
                    $date = date('d/m/Y', time());
                }

                $data = $leaveController->executeHouseReport($houseId, $date);

                $argumentCount = count($this->arg);

                /* Export to csv
                */
                if ($this->arg[$argumentCount - 1] == 'export') {
                    $leaveController->exportHouseReport($data);
                } /* Print view
         */
                else {
                    if ($this->arg[$argumentCount - 1] == 'print') {
                        $leaveController->printHouseReport($data);
                    } /* Web view
         */
                    else {
                        $leaveController->renderHouseReport($data);
                    }
                }
            } else {
                if ($method == 'report' && $this->arg[1] == 'catering') {
                    $date = date('d/m/Y', time());
                    if (is_string($this->arg[2]) && strlen($this->arg[2]) > 0 && $this->arg[2] != 'export') {
                        $date = str_replace('-', '/', $this->arg[2]);
                    }

                    $data = $leaveController->executeCateringReport($date);

                    $argumentCount = count($this->arg);

                    if ($this->arg[$argumentCount - 1] == 'export') {
                        $leaveController->exportCateringReport($data);
                    } else {
                        $leaveController->renderCateringReport($data);
                    }
                } else {
                    if ($method == 'processCreate') {
                        $leaveController->processCreateRequest($_POST);
                    } else {
                        if ($method == 'processModify') {
                            $leaveController->processModifyRequest($_POST);
                        } else {
                            if ($method == 'processModifyList') {
                                $leaveController->processModifyRequestList($_POST);
                            } else {
                                if ($method == 'test') {
                                    $leaveController->executeEmailTest();
                                } else {
                                    $data = $leaveController->executeHouseRequestList($_SESSION['houseId']);
                                    $leaveController->renderHouseRequestList($data);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
catch (Exception_Schoolbox_NotFound $e) {
    $this->error(2);
}