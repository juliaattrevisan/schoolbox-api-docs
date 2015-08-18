<?php
use Schoolbox\Resource\Folder\Model as CategoryModel;
use Schoolbox\Resource\Folder\Mapper as CategoryMapper;
use Calendar\Event\Collection;
use Calendar\Event\Type\Day\Event;


/**
 * Event type that represents events for a room.
 *
 * This shouldn't be used as a calendar connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Calendar_Api_EventType_Timetable_Room extends Calendar_Api_EventType_Abstract
{

    /**
     * @var External_TimetableInterface
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
    public function __construct(\Schoolbox\User $user, $config)
    {
        $this->setUser($user);
        $this->externalTimetable = new External_Timetable();
    }

    /**
     * Get day events from this data source
     *
     * @param $startTimestamp
     * @param $endTimestamp
     * @return Collection
     */
    public function getEvents($startTimestamp, $endTimestamp)
    {
        $stack = new Collection();

        $roomTimetable = $this->externalTimetable->getRoomTimetable($this->roomCode);

        // Scan through each day from $startTimestamp to $endTimestamp, figuring
        // out that day's timetable day number for each of the user's campuses.

        $campusList       = array();
        $userList         = array();
        $categoryList     = array();
        $currentTimestamp = $startTimestamp;
        $dayNumberList    = array();
        while ($currentTimestamp < $endTimestamp) {
            $currentTime = new DateTime();
            $currentTime->setTimestamp($currentTimestamp);
            // Get the definition for this day only.
            foreach ($roomTimetable as $room) {
                // What's the day number for the campus that has booked this room?
                // If for some reason I can't find it, get timetable for a null campus
                $timetableCode = $room['TimetableGroup'];
                if (!array_key_exists($timetableCode, $campusList)) {
                    try {
                        $campusList[$timetableCode] = Schoolbox\CampusMapper::getInstance()->findOneByColumn(
                            'ext_timetable_code',
                            $timetableCode
                        );
                    } catch (Schoolbox\Mapper\Exception $e) {
                        $campusList[$timetableCode] = null;
                    }
                }
                $campus   = null;
                $campusId = null;
                if ($campusList[$timetableCode] instanceof Schoolbox\Campus) {
                    $campus   = $campusList[$timetableCode];
                    $campusId = $campusList[$timetableCode]->getId();
                }

                // Cache lookups for external timetable
                if (!isset($dayNumberList[$currentTimestamp][$campusId])) {
                    $dayNumberList[$currentTimestamp][$campusId] = $this->getExternalTimetable()->getDayNumber(
                        $campus,
                        $currentTimestamp
                    );
                }
                $dayNumber = $dayNumberList[$currentTimestamp][$campusId];

                if ($room['DayNumber'] != $dayNumber) {
                    continue;
                }

                // Does this event overlap timespan $startTimestamp to $endTimestamp?
                $classStartTime = clone $currentTime;
                $classEndTime   = clone $currentTime;
                $classStartTime->modify($room['PeriodStart']);
                $classEndTime->modify($room['PeriodEnd']);
                if ($classEndTime->getTimestamp() < $startTimestamp) {
                    continue;
                }
                if ($classStartTime->getTimestamp() > $endTimestamp) {
                    continue;
                }

                $event = new Event();
                // Make this up as type: campus for now
                // It also has a category.
                $event->setType(Event::CAMPUS_ACCESS);
                $event->setAllday(false);
                $event->setLocation($room['RoomCode']);
                $event->setStartTime(new Alaress_Date_Format($classStartTime));
                $event->setEndTime(new Alaress_Date_Format($classEndTime));
                // Let's say the event doesn't have a publish date,
                // this gets around issue SB-5493 (where events that haven't
                // occured yet only show to superusers)
                $event->setPublishDate(null);
                $event->setTitle($room['Description']);
                $event->setCampusList($campusId);

                // Load room and category if they aren't already there.
                $userExternalId = $room['StaffID'];
                if (!(array_key_exists($userExternalId, $userList))) {
                    try {
                        $userList[$userExternalId] = Schoolbox\UserMapper::getInstance()->findOneByColumn(
                            'synergy_id',
                            $userExternalId
                        );
                    } catch (Schoolbox\Mapper\Exception $e) {
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
                        $categoryList[$categoryCode] = CategoryMapper::getInstance()->findByCode(
                            $categoryCode
                        );
                    } catch (Schoolbox\Mapper\Exception $e) {
                        $categoryList[$categoryCode] = null;
                    }
                }
                if ($categoryList[$categoryCode] instanceof CategoryModel) {
                    $event->setCategoryId($categoryList[$categoryCode]->getId());
                    $event->setCategoryName($categoryList[$categoryCode]->getName());
                }
//                $event->setColour($period['color']);
                $event->setConnectorId($this->getConnectorId());
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
     * @return Collection
     */
    public function getEvent($eventId)
    {
        // Blah
        $stack = new Collection();
        return $stack;
    }

    /**
     * @param  \External_TimetableInterface $externalTimetable
     *
     * @return $this
     */
    public function setExternalTimetable($externalTimetable)
    {
        $this->externalTimetable = $externalTimetable;
        return $this;
    }

    /**
     * @return \External_TimetableInterface
     */
    public function getExternalTimetable()
    {
        return $this->externalTimetable;
    }

    /**
     * @param  string $roomCode
     * @return $this
     */
    public function setRoomCode($roomCode)
    {
        $this->roomCode = $roomCode;
        return $this;
    }

    /**
     * @return string
     */
    public function getRoomCode()
    {
        return $this->roomCode;
    }
}
