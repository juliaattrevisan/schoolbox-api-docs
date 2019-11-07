<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Event\Attendance;


use Alaress\Specification\Base;
use Event_Attendance_Controller;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Error\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;

/**
 * CanModifyAttendance
 *
 * Check whether a user has permission to modify attendance for an event
 *
 * @package Event\Attendance
 */
class CanModifyAttendance extends Base
{
    /**
     * @var Event_Attendance_Controller
     */
    private $attendanceController;

    /**
     * @var EventMapper
     */
    private $eventMapper;

    /**
     * @var User
     */
    private $user;

    /**
     * @var Mapper
     */
    private $errorMapper;

    /**
     * CanModifyAttendance constructor
     * @param Event_Attendance_Controller $attendanceController
     * @param EventMapper                 $eventMapper
     * @param User                        $user
     * @param Mapper                      $errorMapper
     */
    public function __construct(
        Event_Attendance_Controller $attendanceController,
        EventMapper $eventMapper,
        User $user,
        Mapper $errorMapper
    ) {
        $this->attendanceController = $attendanceController;
        $this->eventMapper          = $eventMapper;
        $this->user                 = $user;
        $this->errorMapper          = $errorMapper;
    }

    /**
     * Checks whether a user has permission to modify attendance for an event,
     * Also will throw exception if the event is not exist
     *
     * @param  $eventId
     * @return bool
     * @throws \Exception
     */
    public function isSatisfiedBy($eventId): bool
    {
        try {
            $event = $this->eventMapper->findById($eventId);
        } catch (Exception $e) {
            throw $this->errorMapper->generateException(Mapper::ERROR_EVENT_NOT_FOUND);
        }
        if (!$this->attendanceController->isPermitted($this->user, $event)) {
            throw $this->errorMapper->generateException(Mapper::ERROR_PERM_EVENT_MODIFY);
        }
        return true;
    }
}
