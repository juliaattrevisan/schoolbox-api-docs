<?php
/**
 * Event type that represents events for a room.
 *
 * This shouldn't be used as a calendar connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Calendar_Api_EventType_Timetable_Room extends Calendar_Api_EventType_Abstract {

    /**
     * @var External_Timetable
     */
    protected $externalTimetable = null;

    /**
     * The code for the room to show events for.
     *
     * @var string
     */
    protected $roomCode;

    /**
     * Constructor
     *
     * @param \Schoolbox\User $user
     * @param $config
     */
    public function __construct(\Schoolbox\User $user, $config) {
        $this->setUser($user);
        $this->externalTimetable = new External_Timetable();
    }

    /**
     * Get day events from this data source
     *
     * @param $startTimestamp
     * @param $endTimestamp
     * @return Calendar_Event_Collection
     */
    public function getEvents($startTimestamp, $endTimestamp) {
        $stack = new Calendar_Event_Collection();

        $roomTimetable = $this->externalTimetable->getRoomTimetable($this->roomCode);

        // Scan through each day from $startTimestamp to $endTimestamp, figuring
        // out that day's timetable day number for each of the user's campuses.

        $campusList       = array();
        $userList         = array();
        $categoryList     = array();
        $currentTimestamp = $startTimestamp;
        while ($currentTimestamp <= $endTimestamp) {

            // Get the definition for this day only.
            foreach ($roomTimetable as $room) {

                // What's the day number for the campus that has booked this room?
                $timetableCode = $room['TimetableGroup'];
                if (!array_key_exists($timetableCode, $campusList)) {
                    $campusList[$timetableCode] = \Schoolbox\CampusMapper::getInstance()->findOneByColumn('ext_timetable_code', $timetableCode);
                }
                $campusId  = $campusList[$timetableCode]->getId();
                $dayNumber = $this->getExternalTimetable()->getDayNumber($campusId, $currentTimestamp);

                if ($room['DayNumber'] != $dayNumber) {
                    continue;
                }

                // Does this event overlap timespan $startTimestamp to $endTimestamp?
                $classStart = strtotime($room['PeriodStart'], $currentTimestamp);
                $classEnd   = strtotime($room['PeriodEnd'],   $currentTimestamp);
                if ($classEnd < $startTimestamp) {
                    continue;
                }
                if ($classStart > $endTimestamp) {
                    continue;
                }

                $event = new Calendar_Event_Type_Day();
                // Make this up as type: campus for now
                // It also has a category.
                $event->setType(Calendar_Event_Type_Day::CAMPUS_ACCESS);
                $event->setAllday(false);
                $event->setLocation($this->roomCode);
                $event->setStartTime(new Alaress_Date_Format($classStart, 'U'));
                $event->setEndTime(new Alaress_Date_Format($classEnd, 'U'));
                // Currently event needs a publish date.
                $event->setPublishDate($event->getStartTime());
                $event->setNote($room['Description']);
                $event->setCampusList($campusId);

                // Load room and category if they aren't already there.
                $userExternalId = $room['StaffID'];
                if (!(array_key_exists($userExternalId, $userList))) {
                    try {
                        $userList[$userExternalId] = Schoolbox\UserMapper::getInstance()->findOneByColumn(
                            'synergy_id', $userExternalId
                        );
                    }
                    catch (Schoolbox\Mapper\Exception $e) {
                        $userList[$userExternalId] = null;
                    }
                }
                if ($userList[$userExternalId] instanceof \Schoolbox\User) {
                    $event->setAuthorId($userList[$userExternalId]->getId());
                    $event->setAuthorName($userList[$userExternalId]->formatName());
                }

                $categoryCode = $room['ClassCode'];
                if (!(array_key_exists($categoryCode, $categoryList))) {
                    try {
                        $categoryList[$categoryCode] = \Schoolbox\Resource\CategoryMapper::getInstance()->findByCode(
                            $categoryCode
                        );
                    }
                    catch (Schoolbox\Mapper\Exception $e) {
                        $categoryList[$categoryCode] = null;
                    }
                }
                if ($categoryList[$categoryCode] instanceof \Schoolbox\Resource\Category) {
                    $event->setCategoryId($categoryList[$categoryCode]->getId());
                    $event->setCategoryName($categoryList[$categoryCode]->getName());
                }
//                $event->setColour($period['color']);
                $stack->add($event);
            }

            // Load events for this campus
            $currentTimestamp = strtotime('+1 day', $currentTimestamp);
        }
        return $stack;
    }

    /**
     * Find a particular event from this source
     *
     * @param $eventId
     * @return Calendar_Event_Collection
     */
    public function getEvent($eventId) {
        // Blah
        $stack = new Calendar_Event_Collection();
        return $stack;
    }

    /**
     * @param  \External_Timetable $externalTimetable
     *
     * @return $this
     */
    public function setExternalTimetable($externalTimetable) {
        $this->externalTimetable = $externalTimetable;
        return $this;
    }

    /**
     * @return \External_Timetable
     */
    public function getExternalTimetable() {
        return $this->externalTimetable;
    }

    /**
     * @param  string $roomCode
     * @return $this
     */
    public function setRoomCode($roomCode) {
        $this->roomCode = $roomCode;
        return $this;
    }

    /**
     * @return string
     */
    public function getRoomCode() {
        return $this->roomCode;
    }

}
