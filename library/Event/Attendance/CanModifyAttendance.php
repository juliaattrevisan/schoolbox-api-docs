<?php


namespace Event\Attendance;


use Event_Attendance_Controller;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Error\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;

class CanModifyAttendance
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


    public function isSatisfiedBy(int $eventId): bool
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
