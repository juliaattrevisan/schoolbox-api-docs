<?php

use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Calendar\Event\Collection;
use Calendar\Event\Type\Timetable\Event;
use Schoolbox\User;

/**
 * Event type that represents events for a room.
 *
 * This shouldn't be used as a calendar connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class Calendar_Api_EventType_Timetable_Room extends Calendar_Api_EventType_Abstract
{

    /**
     * @var External_TimetableInterface
     */
    private $externalTimetable;

    /**
     * A map of codes to resource IDs for the room to show events for.
     *
     * * key:   the room code
     * * value: the resource ID
     *
     * @var array
     */
    private $roomCodes;


    /**
     * Constructor
     *
     * @param int                         $connectorId
     * @param User                        $user
     * @param User                        $viewer
     * @param External_TimetableInterface $externalTimetable
     * @param array                       $roomCodes // TODO what type of array? Calls to this constructor are utilising various hacks.
     *                                               // TODO This should document how to not filter by room code at all.
     *                                               // TODO For consistency with other connectors I suggest is should be:
     *                                                        null  => no filter (ie all),
     *                                                        []    => return nothing,
     *                                                        [x,y] => filter to x and y
     */
    public function __construct(int $connectorId, User $user, User $viewer, External_TimetableInterface $externalTimetable, $roomCodes = [])
    {
        parent::__construct($connectorId, $user, $viewer);
        $this->externalTimetable = $externalTimetable;
        $this->roomCodes         = $roomCodes;
    }


    /**
     * Get day events from this data source
     *
     * @param int        $startTimestamp
     * @param int        $endTimestamp
     * @param array|null $allowedEventTypes
     * @return Collection
     * @throws InvalidArgumentException
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): Collection
    {
        $stack = new Collection();

        $roomTimetable = $this->externalTimetable->getRoomTimetable(array_keys($this->roomCodes));

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
//                        trigger_error(
//                            sprintf('Campus code "%s" does not exist - no timetabled classes will be displayed', $timetableCode),
//                            E_USER_WARNING
//                        );
                        $campusList[$timetableCode] = null;
                    }
                }

                // Only load timetabled classes for rooms which are in a campus that exists.
                // Without a known campus I can't figure out what day to apply the room timetable to.
                if ($campusList[$timetableCode] instanceof Schoolbox\Campus) {
                    $campus   = $campusList[$timetableCode];
                    $campusId = $campusList[$timetableCode]->getId();
                } else {
                    continue;
                }

                // Cache lookups for external timetable
                if (!isset($dayNumberList[$currentTimestamp][$campusId])) {
                    $dayNumberList[$currentTimestamp][$campusId] = $this->externalTimetable->getDayNumber(
                        $campus,
                        $currentTimestamp
                    );
                }
                $dayNumber = $dayNumberList[$currentTimestamp][$campusId];

                if ($room['DayNumber'] != $dayNumber || null === $room['PeriodStart'] || null === $room['PeriodEnd']) {
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

                /* TODO where is ->setEventType() at?
                 * TODO is this even used?
                 */
                $event = new Event();
                // Make this up as type: campus for now
                // It also has a category.
                $event->setType(Event::CAMPUS_ACCESS);
                $event->setAllDay(false);
                $event->setLocation($room['RoomCode']);
                $event->setStartTime(new Alaress_Date_Format($classStartTime));
                $event->setEndTime(new Alaress_Date_Format($classEndTime));
                // Let's say the event doesn't have a publish date,
                // this gets around issue SB-5493 (where events that haven't
                // occured yet only show to superusers)
                $event->setPublishDate(null);
                $event->setTitle($room['Description'] . (!empty((string)$room['ClassCode']) ? ' (' . $room['ClassCode'] . ')' : ''));
                $event->setCampusList($campusId);
                if (isset($this->roomCodes[$room['RoomCode']])) {
                    $event->setResourceId($this->roomCodes[$room['RoomCode']]);
                }

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

                $key = ($room['ClassCampus'] ?? '') . $room['ClassCode'];
                if ($room['ClassCode'] && $key !== '' && !(array_key_exists($key, $categoryList))) {
                    $categoryList[$key] = FolderMapper::getInstance()->findForCampusAndClass(
                        $room['ClassCampus'] ?? '',
                        $room['ClassCode']
                    );
                }
                if (($categoryList[$key] ?? null) instanceof FolderModel) {
                    $event->setCategoryId($categoryList[$key]->getId());
                    $event->setCategoryName($categoryList[$key]->getName());
                }
                // Setting it to light colour for now.
                $event->setColour('#c1e5ff');
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
     * @param int $eventId
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
}
