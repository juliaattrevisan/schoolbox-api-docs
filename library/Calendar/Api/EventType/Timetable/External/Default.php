<?php
use Schoolbox\Resource\Folder\Model as CategoryModel;
use Calendar\Event\Collection;
use Calendar\Event\Type\Timetable\Event;


/**
 * Event type that represents events from the external timetable handler.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Calendar_Api_EventType_Timetable_External_Default extends Calendar_Api_EventType_Abstract
{

    /**
     * @var External_Timetable
     */
    protected $externalTimetable = null;

    /**
     * Constructor
     *
     * @param \Schoolbox\User $user
     * @param                 $config
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
     *
     * @return Collection
     */
    public function getEvents($startTimestamp, $endTimestamp)
    {
        $dayTimetable     = array();
        $currentTimestamp = $startTimestamp;

        // Colors come from the timetable color mapper.
        // Associate them the same way they would be in the timetable screen.
        $colorList  = Timetable_ColourMapper::getInstance()->findAll();
        $colorMap   = array();
        $colorIndex = 0;

        // Figure out event type
        try {
            /** @var Calendar_Api_Config_Record $config */
            $config        = $this->loadConnectorConfiguration();
            $decodedConfig = $config->decodeConfig();
            $eventType     = $decodedConfig->getLuEventType();
        } catch (\Schoolbox\Mapper\Exception $e) {
            $eventType = 1;
        }

        // Scan through each day from $startTimestamp to $endTimestamp, figuring
        // out that day's timetable day number for each of the user's campuses.
        /** @var Calendar_Event_Type_Day[] $addedEvents */
        $addedEvents = array();
        while ($currentTimestamp < $endTimestamp) {
            foreach ($this->getUser()->getCampusCollection() as $campus) {
                $campusId  = $campus->getId();
                $dayNumber = $this->getExternalTimetable()->getDayNumber($campus, $currentTimestamp);
                if ($dayNumber <= 0) {
                    continue;
                }
                // Building the timetable is an expensive process,
                // so only do when necessary, and only once for each day/campus
                if (!isset($dayTimetable[$dayNumber][$campusId])) {
                    $dayTimetable[$dayNumber][$campusId] = $this->buildTimetable($campus, $dayNumber);
                }
                // Load events for this campus
                if (isset($dayTimetable[$dayNumber][$campusId])) {
                    foreach ($dayTimetable[$dayNumber][$campusId] as $period) {
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
                            $event->setAllday(false);
                            $event->setLocation($period['room']);
                            $event->setStartTime(new Alaress_Date_Format($startTime, 'U'));
                            $event->setEndTime(new Alaress_Date_Format($endTime, 'U'));
                            $event->setPublishDate(null);
                            $event->setTitle(sprintf('%s (%s)', $period['description'], $code));
                            $event->setCampusList($campusId);
                            if ($period['staff'] instanceof Schoolbox\User) {
                                $event->setAuthorId($period['staff']->getId());
                                $event->setAuthorName($period['staff']->formatName());
                            }
                            if ($period['category'] instanceof CategoryModel) {
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
            }
            $currentTimestamp = strtotime('+1 day', $currentTimestamp);
        }
        $stack = new Collection();
        $stack->setStack(array_values($addedEvents));
        return $stack;
    }

    /**
     * Builds the timetable for a given campus for a given day.
     *
     * @param \Schoolbox\Campus $campus
     * @param int $day
     *
     * @return array
     */
    protected function buildTimetable($campus, $day)
    {
        try {
            $timetable = $this->getExternalTimetable()->getDayTimetable(
                $this->getUser()->getExternalId(),
                $this->getUser()->getId(),
                $campus,
                $day
            );
        } catch (Schoolbox\External\Exception $e) {
            // Can't fetch timetable? assume it's empty
        }
        if (empty($timetable)) {
            $timetable = array();
        }

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

                // Load category into period defintion
                $categoryId = $period['code'];
                try {
                    if (!empty($categoryId)) {
                        // Don't load more than once
                        if (!array_key_exists($categoryId, $timetableCategories)) {
                            $timetableCategories[$categoryId] =
                                \Schoolbox\Resource\CategoryMapper::getInstance()->findByCode(
                                    $categoryId
                                );
                        }
                    } else {
                        $timetableCategories[$categoryId] = null;
                    }
                } catch (Exception $e) {
                    $timetableCategories[$categoryId] = null;
                }
                $period['category'] = $timetableCategories[$categoryId];

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
        // Blah
        $stack = new Collection();
        return $stack;
    }

    /**
     * @param  \External_Timetable $externalTimetable
     *
     * @return $this
     */
    public function setExternalTimetable($externalTimetable)
    {
        $this->externalTimetable = $externalTimetable;
        return $this;
    }

    /**
     * @return \External_Timetable
     */
    public function getExternalTimetable()
    {
        return $this->externalTimetable;
    }
}
