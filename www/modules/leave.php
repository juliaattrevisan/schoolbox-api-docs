<?php

use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;

/* Routing */
/** @var SitePage $this */
$leaveController = new Leave_Controller($this);

$method = $this->arg[0] ?? null;

try {
    if (!$method && empty($this->arg)) {
        $leaveController->renderSummary();
    } elseif ($method == 'create') {
        $data = $leaveController->executeCreateRequest();
        $leaveController->renderCreateRequest($data);
    } elseif ($method == 'modify' && is_numeric($this->arg[1])) {
        $data = $leaveController->executeModifyRequest($this->arg[1]);
        $leaveController->renderModifyRequest($data);
    } elseif ($method == 'view' && is_numeric($this->arg[1])) {
        $data = $leaveController->executeModifyRequest($this->arg[1]);
        $leaveController->renderViewRequest($data);
    } elseif ($method == 'student' && is_numeric($this->arg[1])) {
        $data = $leaveController->executeUserRequestList($this->arg[1]);
        $leaveController->renderUserRequestList($data);
    } elseif (($method == 'student') && ($this->arg[1] == 'export') && is_numeric($this->arg[2])) {
        /* Export a csv history of a particular student's leave requests
         */
        $data = $leaveController->executeUserRequestList($this->arg[2]);
        $leaveController->exportUserRequestList($data);
    } elseif (($method == 'house') && ($this->arg[1] == 'export') && is_numeric($this->arg[2])) {
        /* Export a csv history of a particular house's leave requests
         */
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
                $houseId = is_numeric($this->arg[2])
                    ? $this->arg[2]
                    : $_SESSION['houseId'];

                $timespanOptions = Leave_Controller::getTimespanOptions();

                // Determine requested report timespan type
                $timespan = is_string($this->arg[3] ?? null) && in_array($this->arg[3], $timespanOptions)
                    ? $this->arg[3]
                    : Leave_Controller::REPORT_TIMESPAN_WEEKEND;

                // Determine requested report start
                $dateStart = is_string($this->arg[4] ?? null)
                    ? str_replace('-', '/', $this->arg[4])
                    : date('d/m/Y', time());

                // Determine requested report end
                $dateEnd = (Leave_Controller::REPORT_TIMESPAN_RANGE === $timespan && isset($this->arg[5]))
                    ? str_replace('-', '/', $this->arg[5])
                    : null;

                // Catch malformed URLs
                try {
                    /** @var array $data */
                    $data = $leaveController->executeHouseReport($houseId, $timespan, $dateStart, $dateEnd);
                } catch (InvalidArgumentException $iae) {
                    error(156);
                }

                $argumentCount = count($this->arg);

                /* Export to csv
                 */
                if ($this->arg[$argumentCount - 1] == 'export') {
                    $leaveController->exportHouseReport($data);
                } elseif ($this->arg[$argumentCount - 1] == 'print') {
                    /* Print view
                     */
                    $leaveController->printHouseReport($data);
                } else {
                    /* Web view
                     */
                    $leaveController->renderHouseReport($data);
                }
            } else {
                if ($method == 'report' && $this->arg[1] == 'catering') {
                    $date = date('d/m/Y', time());

                    if (is_string($this->arg[2] ?? null) && strlen($this->arg[2]) > 0 && $this->arg[2] != 'export') {
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
                    } elseif ($method == 'processModify') {
                        $leaveController->processModifyRequest($_POST);
                    } elseif ($method == 'processModifyList') {
                        $leaveController->processModifyRequestList($_POST);
                    } elseif ($method == 'test') {
                        $leaveController->executeEmailTest();
                    } else {
                        $data = $leaveController->executeHouseRequestList($_SESSION['houseId']);
                        $leaveController->renderHouseRequestList($data);
                    }
                }
            }
        }
    }
} catch (SchoolboxNotFoundException $e) {
    $this->error(2);
}
