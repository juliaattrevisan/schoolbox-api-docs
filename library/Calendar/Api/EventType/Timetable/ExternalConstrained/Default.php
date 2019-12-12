<?php

use Schoolbox\Campus;
use Schoolbox\Resource\Folder\Model as Folder;
use Calendar\Event\Collection;
use Calendar\Event\Type\Timetable\Event;
use Schoolbox\User;
use Schoolbox\IConfigLoader;

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

    /** @var Config\Loader */
    private $configLoader;


    /**
     * @inheritdoc
     */
    public function __construct(int $connectorId, User $user, User $viewer, IConfigLoader $configLoader)
    {
        parent::__construct($connectorId, $user, $viewer);
        $this->externalTimetable = new External_Timetable();
        $this->configLoader = $configLoader;
    }

    /**
     * Get day events from this data source
     *
     * @param $startTimestamp
     * @param $endTimestamp
     *
     * @return Collection
     */
    public function getEvents($startTimestamp, $endTimestamp)
    {
        // we're going to use this as a window for not building *too* much expensive timetable at once
        $startOfDay = Alaress_Date_Format::now()->startOfDay()->getUnixTimeStamp();

        // If the user is not allowed to view timetables return an empty collection
        if ($this->getUser()->getHideTimetable()) {
            $stack = new Collection();
            return $stack;
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
            $eventType = 1;
        }
        $expensiveWindow = strtotime("{$days} day", 0); // convert days to seconds

        // This is a config var that determines how many days in the past you can mark attendance for.
        // Later, we need to fetch these days expensively, because otherwise they come without attendance data.
        $attendanceWindow = strtotime("{$this->configLoader->get('external_attendance_days')} day", 0); // convert days to seconds

        // Scan through each day from $startTimestamp to $endTimestamp, figuring
        // out that day's timetable day number for each of the user's campuses.
        /** @var Event[] $addedEvents */
        $addedEvents = array();
        while ($currentTimestamp < $endTimestamp) {
            $elapsedTime = $currentTimestamp - $startOfDay;

            foreach ($this->getUser()->getCampusCollection() as $campus) {
                $campusId  = $campus->getId();

                $date = new Alaress_Date_Format($currentTimestamp, Alaress_Date_Format::TIMESTAMP);

                if ($elapsedTime >= (-$attendanceWindow) && $elapsedTime < $expensiveWindow) {
                    // if the day we're looking at is
                    // - no more than $attendanceWindow in the past, and
                    // - no more than $expensiveWindow in the future,
                    // get the expensive timetable
                    $dayTimetable = $this->buildTimetableExpensively($this->getUser(), $campus, $date);

                } else {
                    $dayNumber = $this->externalTimetable->getDayNumber($campus, $currentTimestamp);

                    if ($dayNumber <= 0) {
                        continue;
                    }

                    $dayTimetable = $this->buildTimetableCheaply($this->getUser(), $campus, $dayNumber);
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
                                $addedEvents[$key]->getCampusList().','.$campusId
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
                            $event->setCategoryId($period['category']->getId());
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
                        $hasAttendancePeriods = !empty($period['AttendancePeriods']);
                        $event->setHasAttendance(!empty($period['AttendanceEnabled']) && $hasAttendancePeriods);
                        $event->setCode($period['code']);
                        $event->setCampus($period['campus']);
                        $event->setPeriod($period['periodNumber']);
                        $event->setAttendancePeriods($period['AttendancePeriods'] ?? []);
                        $event->setPeriodName($period['periodName']);
                        $event->setFileType('A');

                        // Load the color for this timetabled class
                        if ($period['code'] && !array_key_exists($period['code'], $colorMap)) {
                            // not enough colors: loop around to index 0.
                            if (!$colorList->offsetExists($colorIndex)) {
                                $colorIndex = 0;
                            }
                            $colorMap[$period['code']] = $colorList->offsetGet($colorIndex)->getHexColour();
                            $colorIndex++;
                        }
                        $event->setColour($colorMap[$period['code']]);

                        $event->setConnectorId($this->getConnectorId());
                        $addedEvents[$key] = $event;
                    }
                }
            }
            $currentTimestamp = strtotime('+1 day', $currentTimestamp);
        }
        $stack = new Collection();
        $stack->setStack(array_values($addedEvents));
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
     * @param Schoolbox\User $user
     * @param Campus         $campus
     * @param int            $dayNumber
     * @return array
     */
    private function buildTimetableCheaply(Schoolbox\User $user, Campus $campus, $dayNumber): array
    {
        if (!$user->getExternalId()) {
            return [];
        }
        try {
            // we are aware of the deprecation of this method, but must use it regardless
            /** @noinspection PhpDeprecationInspection */
            $timetable = $this->externalTimetable->getDayTimetable(
                $user->getExternalId(),
                $user->getId(),
                $campus,
                $dayNumber
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

                // Load staff into period defintion
                $staffId = $period['staffID'];
                try {
                    if (!empty($staffId)) {
                        // Don't load more than once
                        if (!array_key_exists($staffId, $timetableStaff)) {
                            $timetableStaff[$staffId] = \Schoolbox\UserMapper::getInstance()->findOneByColumn(
                                'synergy_id',
                                $period['staffID']
                            );
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
     * @param $eventId
     *
     * @return Collection
     */
    public function getEvent($eventId)
    {
        // No events in this strat, just return empty.
        return new Collection();
    }

}
