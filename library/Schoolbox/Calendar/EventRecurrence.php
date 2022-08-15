<?php

/**
 * EventRecurrence
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar;

use Alaress_Date_Format;
use InvalidArgumentException;
use Schoolbox\Collection;

class EventRecurrence
{

    /**
     * Possible values for recurrence
     *
     * @var array
     */
    private $validRepeat = array(
        'daily' => '+1 day',
        'weekly' => '+1 week',
        'fortnightly' => '+2 weeks',
        'monthly' => '+1 month',
        'yearly'  => '+1 year'
    );

    /**
     * A calendar handler
     *
     * @var EventMapper
     */
    private $calendarHandler = null;

    /**
     * Maximum number of times an event can recurrrrr
     *
     * @var int
     */
    private $maxNumRecurring = 100;

    /**
     * Series id
     *
     * @var int
     */
    private $seriesId = null;

    /**
     * An array of Alaress_Date_Format objects which indicate when a
     * recurring event incident begins and ends
     *
     * @var Alaress_Date_Format[]
     */
    private $dateCollection = array();

    /**
     * Efficacy setting chosen
     *
     * @var string
     */
    private $efficacy = '';

    /**
     * Possible efficacy values
     *
     * @var string[]
     */
    private $efficacyValues = [ EventRecurrenceMapper::EFFICACY_SERIES, EventRecurrenceMapper::EFFICACY_SELF ];

    /**
     * The chosen frequency of event recurrence (daily, weekly, etc)
     * as selected by the user
     *
     * @var string
     */
    private $repeatSetting = '';

    /**
     * Recur events until this date
     * An instance of Alaress_Date_Format
     *
     * @var Alaress_Date_Format
     */
    private $until = null;

    /**
     * An instance of Alaress_Date_Format
     *
     * @var Alaress_Date_Format
     */
    private $startDate = null;

    /**
     * An instance of Alaress_Date_Format
     *
     * @var Alaress_Date_Format
     */
    private $endDate = null;

    /**
     * Constructor
     *
     * @param EventMapper $mapper
     * @param string $efficacy
     * @param string $repeatSetting
     * @param Alaress_Date_Format $until
     * @param Alaress_Date_Format $startDate
     * @param Alaress_Date_Format $endDate
     */
    public function __construct($mapper, $efficacy, $repeatSetting, $until, Alaress_Date_Format $startDate, Alaress_Date_Format $endDate)
    {
        $this->setCalendarHandler($mapper);
        $this->setDateCollection(new Collection());
        $this->setRepeatSetting($repeatSetting);
        $this->setUntil($until);
        $this->setStartDate($startDate);
        $this->setEndDate($endDate);

        /* Used for recurring events - determines how broadly
         * changes are made to event details etc
         */
        if (in_array($efficacy, $this->getEfficacyValues())) {
            $this->setEfficacy($efficacy);
        }

        if ($this->isValidInterval() && $this->getUntil()->getUnixTimeStamp()) {
            // repeat specified, and and enddate also, populate the dates to be set
            $this->setSeriesId($this->getCalendarHandler()->getUpperSeriesIndex());

            $start_timestamp = $this->getStartDate()->getUnixTimeStamp();
            $end_timestamp = $this->getEndDate()->getUnixTimeStamp();

            // Repeat of events: repeat until the start date is after the until
            // date (or x100, whatever is less)
            for ($i = 0; ($start_timestamp <= $this->getUntil()->getUnixTimeStamp()) && ($i < $this->getMaxNumRecurring()); $i++) {
                $this_start_date = new Alaress_Date_Format($start_timestamp, 'U');
                $this_end_date = new Alaress_Date_Format($end_timestamp, 'U');

                $this->getDateCollection()->add(array(
                    'start' => $this_start_date->getDatabaseDate(),
                    'end' => $this_end_date->getDatabaseDate(),
                ));

                /* Increment to the next incident of the event
                 */
                $start_timestamp = strtotime($this->intervalValue(), $start_timestamp);
                $end_timestamp = strtotime($this->intervalValue(), $end_timestamp);
            }
        } elseif ($this->isValidInterval() && (!$this->getUntil()->getUnixTimeStamp())) {
            // if repeat is set, but no enddate was specified, bail out
            // "not enough data submitted to the form"
            throw new InvalidArgumentException("Repeat was set but no end-date specified", \Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
        } else {
            // no repeat
            $this->getDateCollection()->add(array(
                'start' => $this->getStartDate()->getDatabaseDate(),
                'end' => $this->getEndDate()->getDatabaseDate(),
            ));
        }
    }

    /**
     * Get a strtotime interval from a chosen setting
     *
     * @return mixed
     */
    private function intervalValue()
    {
        return $this->validRepeat[$this->getRepeatSetting()];
    }

    /**
     * Whether the interval setting chosen is valid
     *
     * @return bool
     */
    private function isValidInterval()
    {
        if (isset($this->validRepeat[$this->getRepeatSetting()])) {
            return true;
        }

        return false;
    }

    /* Getters/Setters
     */

    /**
     * Set calendar handler
     *
     * @param EventMapper $calendarHandler
     */
    private function setCalendarHandler($calendarHandler)
    {
        $this->calendarHandler = $calendarHandler;
    }

    /**
     * Get calendar handler
     *
     * @return EventMapper
     */
    private function getCalendarHandler()
    {
        return $this->calendarHandler;
    }

    /**
     * Set max num recurring
     *
     * @param int $maxNumRecurring
     */
    private function setMaxNumRecurring($maxNumRecurring)
    {
        $this->maxNumRecurring = $maxNumRecurring;
    }

    /**
     * Get max num recurring
     *
     * @return int
     */
    private function getMaxNumRecurring()
    {
        return $this->maxNumRecurring;
    }

    /**
     * Set series id
     *
     * @param int $seriesId
     */
    private function setSeriesId($seriesId)
    {
        $this->seriesId = $seriesId;
    }

    /**
     * Get series id
     *
     * @return int
     */
    public function getSeriesId()
    {
        return $this->seriesId;
    }

    /**
     * Set repeat setting
     *
     * @param string $repeatSetting
     */
    private function setRepeatSetting($repeatSetting)
    {
        $this->repeatSetting = $repeatSetting;
    }

    /**
     * Get repeat setting
     *
     * @return string
     */
    private function getRepeatSetting()
    {
        return $this->repeatSetting;
    }

    /**
     * Set date collection
     *
     * @param Collection|Alaress_Date_Format[] $dateCollection
     */
    private function setDateCollection($dateCollection)
    {
        $this->dateCollection = $dateCollection;
    }

    /**
     * Get date collection
     *
     * @return Collection|Alaress_Date_Format[]
     */
    public function getDateCollection()
    {
        return $this->dateCollection;
    }

    /**
     * Set efficacy
     *
     * @param string $efficacy
     */
    private function setEfficacy($efficacy)
    {
        $this->efficacy = $efficacy;
    }

    /**
     * Get efficacy
     *
     * @return string
     */
    private function getEfficacy()
    {
        return $this->efficacy;
    }

    /**
     * Set efficacy values
     *
     * @param string $efficacyValues
     */
    private function setEfficacyValues($efficacyValues)
    {
        $this->efficacyValues = $efficacyValues;
    }

    /**
     * Get efficacy values
     *
     * @return string[]
     */
    private function getEfficacyValues()
    {
        return $this->efficacyValues;
    }

    /**
     * Set until
     *
     * @param string $until
     */
    private function setUntil($until)
    {
        $this->until = $until;
    }

    /**
     * Get until
     *
     * @return Alaress_Date_Format
     */
    private function getUntil()
    {
        return $this->until;
    }

    /**
     * Set start date
     *
     * @param Alaress_Date_Format $startDate
     */
    private function setStartDate($startDate)
    {
        $this->startDate = $startDate;
    }

    /**
     * Get start date
     *
     * @return Alaress_Date_Format
     */
    private function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * Set end date
     *
     * @param Alaress_Date_Format $endDate
     */
    private function setEndDate($endDate)
    {
        $this->endDate = $endDate;
    }

    /**
     * Get end date
     *
     * @return Alaress_Date_Format
     */
    private function getEndDate()
    {
        return $this->endDate;
    }
}
