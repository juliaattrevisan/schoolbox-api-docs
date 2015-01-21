<?php

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
     * @return Calendar_Event_Collection
     */
    public function getEvents($startTimestamp, $endTimestamp)
    {
        $stack            = new Calendar_Event_Collection();
        $dayTimetable     = array();
        $currentTimestamp = $startTimestamp;

        // Colors come from the timetable color mapper.
        // Associate them the same way they would be in the timetable screen.
        $colorList  = Timetable_ColourMapper::getInstance()->findAll();
        $colorMap   = array();
        $colorIndex = 0;

        // For efficiency - fetch the full timetable, and build its color list
        foreach ($this->getUser()->getCampusCollection() as $campus) {
            $campusId  = $campus->getId();
            $timetable = $this->getExternalTimetable()->getFullTimetable(
                $this->getUser()->getExternalId(),
                $this->getUser()->getId(),
                $campusId
            );
            if (empty($timetable)) {
                continue;
            }
            foreach ($timetable as $periodDetail) {
                foreach ($periodDetail['day'] as $dayNumber => $dayDetail) {
                    foreach ($dayDetail as $classDetail) {
                        // Build the day timetable detail.
                        $period             = $periodDetail + $classDetail;
                        $period['staff']    = null;
                        $period['category'] = null;
                        try {
                            if (!empty($period['staffID'])) {
                                $period['staff'] = \Schoolbox\UserMapper::getInstance()->findOneByColumn(
                                    'synergy_id',
                                    $period['staffID']
                                );
                            }
                        } catch (Exception $e) {
                            // noop
                        }
                        try {
                            if (!empty($period['code'])) {
                                $period['category'] = \Schoolbox\Resource\CategoryMapper::getInstance()->findByCode(
                                    $period['code']
                                );
                            }
                        } catch (Exception $e) {
                            // noop
                        }

                        // And set the color map as well.
                        if (count($dayDetail) > 0) {
                            // Set this into the color map
                            if ($period['code'] && !array_key_exists($period['code'], $colorMap)) {
                                // not enough colors: loop around to index 0.
                                if (!$colorList->offsetExists($colorIndex)) {
                                    $colorIndex = 0;
                                }
                                $colorMap[$period['code']] = $colorList->offsetGet($colorIndex)->getHexColour();
                                $colorIndex++;
                            }
                            $period['color'] = $colorMap[$period['code']];
                        }
                        $dayTimetable[$dayNumber][$campusId][] = $period;
                    }
                }
            }
        }

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
        while ($currentTimestamp < $endTimestamp) {
            foreach ($this->getUser()->getCampusCollection() as $campus) {
                $campusId  = $campus->getId();
                $dayNumber = $this->getExternalTimetable()->getDayNumber($campusId, $currentTimestamp);
                if ($dayNumber <= 0) {
                    continue;
                }
                // Load events for this campus
                if (isset($dayTimetable[$dayNumber][$campusId])) {
                    foreach ($dayTimetable[$dayNumber][$campusId] as $period) {
                        if (isset($period['description'])) {
                            $event = new Calendar_Event_Type_Day();
                            $event->setAllday(false);
                            $event->setLocation($period['room']);
                            $event->setStartTime(
                                new Alaress_Date_Format(strtotime($period['periodStart'], $currentTimestamp), 'U')
                            );
                            $event->setEndTime(
                                new Alaress_Date_Format(strtotime($period['periodEnd'], $currentTimestamp), 'U')
                            );
                            $event->setPublishDate(null);
                            $event->setTitle($period['description']);
                            $event->setCampusList($campusId);
                            if ($period['staff'] instanceof Schoolbox\User) {
                                $event->setAuthorId($period['staff']->getId());
                                $event->setAuthorName($period['staff']->formatName());
                            }
                            if ($period['category'] instanceof Schoolbox\Resource\Category) {
                                // Put this into a category
                                $event->setCategoryId($period['category']->getId());
                                $event->setCategoryName($period['category']->getName());

                                // Finagle a category collection for this event
                                $eventCategory = new \Schoolbox\Calendar\Relation\Category();
                                $eventCategory->setCategoryId($period['category']->getId());
                                $event->setCategoryCollection(
                                    new Schoolbox\Collection(array($eventCategory))
                                );
                                $event->setAccess(Calendar_Event_Type_Day::GROUP_ACCESS);
                            } else {
                                // Make this up as a campus event if it has no category
                                $event->setAccess(Calendar_Event_Type_Day::CAMPUS_ACCESS);
                            }
                            $event->setType($eventType);
                            $event->setColour($period['color']);
                            $event->setConnectorId($this->getConnectorId());
                            $stack->add($event);
                        }
                    }
                }
            }
            $currentTimestamp = strtotime('+1 day', $currentTimestamp);
        }
        return $stack;
    }

    /**
     * Find a particular event from this source
     *
     * @param $eventId
     *
     * @return Calendar_Event_Collection
     */
    public function getEvent($eventId)
    {
        // Blah
        $stack = new Calendar_Event_Collection();
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
