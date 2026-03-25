<?php

use Calendar\Event\Type\EventInterface;
use Schoolbox\Campus;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder\Model as Folder;
use Calendar\Event\Type\Timetable\Event;
use Schoolbox\User;
use Schoolbox\IConfigLoader;
use Schoolbox\UserMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;

/**
 * Event type that represents events from the external timetable handler.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class Calendar_Api_EventType_Timetable_ExternalConstrained_Default extends Calendar_Api_EventType_Abstract
{
    /**
     * @var External_Timetable
     */
    private $externalTimetable;

    /** @var IConfigLoader */
    private $configLoader;

    /**
     * @var Access
     */
    private $access;

    /**
     * @var \Schoolbox\Calendar\EventTypeMapper
     */
    private \Schoolbox\Calendar\EventTypeMapper $eventTypeMapper;


    /**
     * @inheritdoc
     */
    public function __construct(
        int $connectorId,
        User $user,
        User $viewer,
        IConfigLoader $configLoader,
        \Schoolbox\Calendar\EventTypeMapper $eventTypeMapper,
        Access $access,
    ) {
        parent::__construct($connectorId, $user, $viewer);
        $this->externalTimetable = new External_Timetable();
        $this->configLoader = $configLoader;
        $this->eventTypeMapper = $eventTypeMapper;
        $this->access = $access;
    }


    /**
     * Get day events from this data source
     *
     * @param int        $startTimestamp
     * @param int        $endTimestamp
     * @param array|null $allowedEventTypes
     * @return GenericCollection<EventInterface>
     * @throws InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): GenericCollection
    {
        // we're going to use this as a window for not building *too* much expensive timetable at once
        $startOfDay = Alaress_Date_Format::now()->startOfDay()->getUnixTimeStamp();

        // If the user is not allowed to view timetables return an empty collection
        if ($this->getUser()->getHideTimetable()) {
            return new GenericCollection(EventInterface::class);
        }

        $currentTimestamp = $startTimestamp;

        // Colors come from the timetable color mapper.
        // Associate them the same way they would be in the timetable screen.
        $colorList  = Timetable_ColourMapper::getInstance()->findAll();
        $colorMap   = array();
        $colorIndex = 0;

        // Figure out event type and days to fetch calendar for
        $days = 0;
        try {
            /** @var Calendar_Api_Config_Record $config */
            $config        = $this->loadConnectorConfiguration();
            $decodedConfig = $config->decodeConfig();
            $eventType     = $decodedConfig->getLuEventType();
            // it should always be one of these, but test
            if ($decodedConfig instanceof Calendar_Api_Config_Connector_ExternalConstrained) {
                // ensure this value is a non-negative integer
                $days = max(0, $decodedConfig->getDuration());
            }
        } catch (\Schoolbox\Mapper\Exception $e) {
            /* Something went awry with loadConnectorConfiguration()
             * Can't determine what event type this would be
             */
            $eventType = null;
        }

        /* Ensure $eventType is valid, finding first available one if it is invalid.
         * If you can't find one, we can't assign anything to the events from this subscription
         * so just return the empty stack; there's no way we could categorise those events
         *
         * getLuEventType() can return a deleted event type identifier.
         */
        $eventType = $this->eventTypeMapper->findFirstMeaningfulId($eventType);

        /* Do a detection on $allowedEventTypes, if provided and meaningful
         */
        if (null !== $allowedEventTypes && \count($allowedEventTypes)) {
            if (!\in_array((int)$eventType, $allowedEventTypes, true)) {
                return new GenericCollection(EventInterface::class);
            }
        }

        $externalAttendanceLink = null;
        $expensiveWindow = strtotime("{$days} day", 0); // convert days to seconds

        // This is a config var that determines how many days in the past you can mark attendance for.
        // Later, we need to fetch these days expensively, because otherwise they come without attendance data.
        $attendanceWindow = strtotime("{$this->configLoader->get('external_attendance_days')} day", 0); // convert days to seconds
        if ($this->getUser()->getRole()->isStaff() && $this->configLoader->get('external_attendance')) {
            $externalAttendanceLink = new External_AttendanceLink($this->configLoader);
            $externalAttendanceLink->init($this->configLoader, $this->getUser()->getRole()->isStaff());
        }

        // Scan through each day from $startTimestamp to $endTimestamp, figuring
        // out that day's timetable day number for each of the user's campuses.
        /** @var Event[] $addedEvents */
        $addedEvents = array();
        while ($currentTimestamp < $endTimestamp) {
            // elapsedTime: negative - past, positive - future
            $elapsedTime = $currentTimestamp - $startOfDay;
            // 60 * 60 * 24 = 86400
            $showAttendance = $externalAttendanceLink && ($elapsedTime < 86400) && ($elapsedTime >= (-$attendanceWindow));
            $showExpensive  = $elapsedTime >= 0 && $elapsedTime < $expensiveWindow;

            foreach ($this->getUser()->getCampusCollection() as $campus) {
                $campusId  = $campus->getId();

                $date = new Alaress_Date_Format($currentTimestamp, Alaress_Date_Format::TIMESTAMP);
                $dayNumber = $this->externalTimetable->getDayNumber($campus, $currentTimestamp);
                if ($dayNumber <= 0) {
                    continue;
                }

                if ($showAttendance || $showExpensive) {
                    // if the day we're looking at is
                    // - no more than $attendanceWindow in the past, and
                    // - no more than $expensiveWindow in the future,
                    // get the expensive timetable
                    $dayTimetable = $this->buildTimetableExpensively($this->getUser(), $campus, $date);
                } else {
                    $dayTimetable = $this->buildTimetableCheaply($this->getUser(), $campus, $dayNumber, $date);
                }

                // Load events for this campus
                foreach ($dayTimetable as $period) {
                    // Has this exact class code for this time been added?
                    // Don't add it again, but do add that it belongs to this campus.
                    if (isset($period['description'])) {
                        $startTime = strtotime($period['periodStart'], $currentTimestamp);
                        $endTime   = strtotime($period['periodEnd'], $currentTimestamp);
                        $code      = $period['code'];
                        $key       = sprintf('%d-%d-%s', $startTime, $endTime, $code);
                        if (array_key_exists($key, $addedEvents)) {
                            $addedEvents[$key]->setCampusList(
                                $addedEvents[$key]->getCampusList() . ',' . $campusId
                            );
                            continue;
                        }


                        $event = new Event();
                        $event->setEventId(md5($key));

                        if ($startTime && $endTime) {
                            // Set the start and end time
                            $event->setStartTime(new Alaress_Date_Format($startTime, Alaress_Date_Format::TIMESTAMP));
                            $event->setEndTime(new Alaress_Date_Format($endTime, Alaress_Date_Format::TIMESTAMP));
                            $event->setAllDay(false);
                        } else {
                            // No start and end time - show this as an all day event
                            $event->setStartTime(clone $date);
                            $event->setEndTime(clone $date);
                            $event->setAllDay(true);
                        }

                        $event->setLocation($period['room']);
                        $event->setPeriodCancelled((bool)($period['ClassCancelled'] ?? false));
                        $event->setPublishDate(null);
                        $event->setTitle(sprintf('%s (%s)', $period['description'], $code));
                        $event->setCampusList($campusId);
                        $event->setTimetableCode($campus->getExtTimetableCode());
                        if ($period['staff'] instanceof Schoolbox\User) {
                            $event->setAuthorId($period['staff']->getId());
                            $event->setAuthorName($period['staff']->formatName());
                        }
                        if ($period['category'] instanceof Folder) {
                            // Put this into a category
                            // Only show link to homepage when user has the correct permission
                            if (
                                $this->access->getFolderAccess(
                                    $this->getViewer(),
                                    $period['category']->getId()
                                ) >= UserAccess::ACCESS_READ
                            ) {
                                $event->setCategoryId($period['category']->getId());
                            }
                            $event->setCategoryName($period['category']->getName());

                            // Finagle a category collection for this event
                            $eventCategory = new \Schoolbox\Calendar\Relation\Category();
                            $eventCategory->setCategoryId($period['category']->getId());
                            $event->setCategoryCollection(
                                new Schoolbox\Collection(array($eventCategory))
                            );
                            $event->setAccess(Event::GROUP_ACCESS);
                        } else {
                            // Make this up as a campus event if it has no category
                            $event->setAccess(Event::CAMPUS_ACCESS);
                        }
                        $event->setType($eventType);

                        // Setup attendance button
                        // The attendance link will only be shown if this period
                        // has periods to mark.
                        $event->setHasAttendance(false);

                        if ($showAttendance && $externalAttendanceLink->isEnabled((string)$period['code'], $period['AttendanceEnabled'] ?? false, $event->getStartTime())) {
                            $attendanceLink = $externalAttendanceLink->getAttendanceLink(
                                (string)$period['code'],
                                (string)$period['campus'],
                                $period['FileType'] ?? '',
                                $period['AttendancePeriods'] ?? [],
                                $period['periodNumber'],
                                $event->getStartTime(),
                                $this->getUser()->getCampusCollection(),
                                $campusId,
                                $this->getUser()->getExternalId(),
                                $dayNumber
                            );
                            if (!empty($attendanceLink)) {
                                $event->setHasAttendance(true);
                                $event->setAttendanceLink($attendanceLink);
                                $event->setAttendanceLinkTarget($externalAttendanceLink->getAttendanceLinkTarget());
                            }
                        }

                        $event->setPeriod($period['periodNumber']);
                        $event->setPeriodName($period['periodName']);

                        /* Load the color for this timetabled class
                         * Treats empty $subjectCode '' as a valid index. Hence all subjects which lack a code will have the same colour.
                         */
                        $colourMapKey = trim($period['code'] ?? '');
                        if (!array_key_exists($colourMapKey, $colorMap)) {
                            // not enough colors: loop around to index 0.
                            if (!$colorList->offsetExists($colorIndex)) {
                                $colorIndex = 0;
                            }
                            $colorMap[$colourMapKey] = $colorList->offsetGet($colorIndex)->getHexColour();
                            $colorIndex++;
                        }
                        $event->setColour($colorMap[$colourMapKey]);

                        $event->setConnectorId($this->getConnectorId());
                        $addedEvents[$key] = $event;
                    }
                }
            }
            $currentTimestamp = strtotime('+1 day', $currentTimestamp);
        }
        $stack = new GenericCollection(EventInterface::class, array_values($addedEvents));
        return $stack;
    }


    /**
     * The expensive implementation of buildTimetable()
     * Use when greater precision of timetable customisations are required (at the expense of performance)
     *
     * @param Schoolbox\User      $user
     * @param Campus              $campus
     * @param Alaress_Date_Format $date
     * @return array
     */
    private function buildTimetableExpensively(Schoolbox\User $user, Campus $campus, Alaress_Date_Format $date): array
    {
        if (!$user->getExternalId()) {
            return [];
        }
        try {
            $timetable = $this->externalTimetable->getTimetableForDate(
                $user->getExternalId(),
                $user->getId(),
                $campus,
                $date
            );
        } catch (Schoolbox\External\Exception $e) {
            // Can't fetch timetable? assume it's empty
            return [];
        }

        return $this->buildTimetable($timetable);
    }


    /**
     * The cheap implementation of buildTimetable()
     * Use when timetable precision is less necessary (eg. more than a couple of days in the future)
     *
     * @param Schoolbox\User      $user
     * @param Campus              $campus
     * @param int                 $dayNumber
     * @param Alaress_Date_Format $date
     * @return array
     */
    private function buildTimetableCheaply(Schoolbox\User $user, Campus $campus, int $dayNumber, Alaress_Date_Format $date): array
    {
        if (!$user->getExternalId()) {
            return [];
        }
        try {
            // we are aware of the deprecation of this method, but must use it regardless
            $timetable = $this->externalTimetable->getDayTimetable(
                $user->getExternalId(),
                $user->getId(),
                $campus,
                $dayNumber,
                $date
            );
        } catch (Schoolbox\External\Exception $e) {
            // Can't fetch timetable? assume it's empty
            return [];
        }

        return $this->buildTimetable($timetable);
    }


    /**
     * Builds the timetable for a given data source. This method should not be called directly,
     * but through either the Cheaply or Expensively variant.
     *
     * @param array $timetable
     * @return array
     */
    private function buildTimetable(array $timetable): array
    {
        $dayTimetable        = array();
        $timetableStaff      = array();
        $timetableCategories = array();
        foreach ($timetable as $periodDetail) {
            if (empty($periodDetail['class'])) {
                continue;
            }
            foreach ($periodDetail['class'] as $classDetail) {
                // Build the day timetable detail.
                $period             = $periodDetail + $classDetail;
                $period['staff']    = null;
                $period['category'] = null;
                unset($period['class']);

                // Load staff into period definition
                $staffId = $period['staffID'];
                try {
                    if (!empty($staffId)) {
                        // Don't load more than once
                        if (!array_key_exists($staffId, $timetableStaff)) {
                            $timetableStaff[$staffId] = UserMapper::getInstance()->findByExternalId($staffId, \Schoolbox\Role::STAFF);
                        }
                    } else {
                        $timetableStaff[$staffId] = null;
                    }
                } catch (Exception $e) {
                    $timetableStaff[$staffId] = null;
                }
                $period['staff'] = $timetableStaff[$staffId];

                // Load category into period definition
                $key = ($period['campus'] ?? '') . $period['code'];
                if (!empty($key)) {
                    // Don't load more than once
                    if (!array_key_exists($key, $timetableCategories)) {
                        $timetableCategories[$key] =
                            \Schoolbox\Resource\Folder\Mapper::getInstance()->findForCampusAndClass(
                                $period['campus'] ?? '',
                                $period['code']
                            );
                    }
                    $period['category'] = $timetableCategories[$key];
                } else {
                    $period['category'] = null;
                }

                $dayTimetable[] = $period;

//                // And set the color map as well.
//                // Set this into the color map
//                if ($period['code'] && !array_key_exists($period['code'], $colorMap)) {
//                    // not enough colors: loop around to index 0.
//                    if (!$colorList->offsetExists($colorIndex)) {
//                        $colorIndex = 0;
//                    }
//                    $colorMap[$period['code']] = $colorList->offsetGet($colorIndex)->getHexColour();
//                    $colorIndex++;
//                }
//                $period['color'] = $colorMap[$period['code']];
//                $dayTimetable[$dayNumber][$campusId][] = $period;
            }
        }
        return $dayTimetable;
    }

    /**
     * Find a particular event from this source
     *
     * @param int $eventId
     *
     * @return GenericCollection<EventInterface>
     */
    public function getEvent($eventId): GenericCollection
    {
        // No events in this strat, just return empty.
        return new GenericCollection(EventInterface::class);
    }
}
