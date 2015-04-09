<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 *
 * This component is deprecated, it was previously used to display a school's campus calendar on a homepage
 *
 */

use Calendar\Event\Collection\Renderer;

class Component_Homepage_CalendarListController extends Component_Homepage_ControllerAbstract
{
    /**
     * @var DeprecatedDatabase
     */
    private $db;

    /**
     * @var Calendar
     */
    private $calendarHandler;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
        $this->db              = DeprecatedDatabase::getInstance();
        $this->calendarHandler = new Calendar();

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'calendarList')) {
            return false;
        }
        $this->parseConfig();

        /* Output a group calendar */
        $this->setBlock($templateHandle, 'campus_event_day', 'campus_event_day_block');
        $this->setBlock('campus_event_day', 'campus_events', 'campus_events_block');


        //event limit changed from posts to days
        /*if ($this->getConfig('size') != 0 && $this->getConfig('size') != '') {
            $where = " AND DATE(start_datetime) < ADDDATE(CURDATE(), INTERVAL {$this->getConfig('size')} DAY) ";
        } else {
            $where = "";
        }*/


        $this->setVar('CALENDAR_TITLE', 'Campus Calendar');

        if($this->getConfig('size')) {
            $days = $this->getConfig('size');
        }
        else {
            $days = 31;
        }

        $now         = Alaress_Date_Format::now();
        $prevDay = false;
        $this->start = $now->startOfDay();
        $this->end   = clone $this->start;
        $this->end->addDays($days);

        // Produce API for schoolbox group events
        $calendarApi  = new Calendar_Api_EventType_Event_Schoolbox_Campus($_SESSION['user'], array());

        // Render all events for group using html renderer
        $eventRenderer = new Renderer($calendarApi, $this->start->getUnixTimestamp(), $this->end->getUnixTimestamp(), $this->user, $this->sitepage);
        foreach ($eventRenderer->getAllEvents() as $currentEvent) {

            //
            // Current day: will never be before the start time
            $currentDay = $currentEvent->getStartTime();
            if ($this->start->compare($currentDay) > 0) {
                $currentDay = $this->start;
            }
            if ($prevDay != false && $currentDay->getDateOnly() != $prevDay->getDateOnly()) {
                $this->parse('campus_event_day_block', 'campus_event_day', true);
                $this->setVar('campus_events_block', '');
            }

            $this->setVar('LIST_DATE', $currentDay->getDateOnly());

            $this->setVar('campus_events', $eventRenderer->renderEvent($currentEvent, 'html'));

            $this->parse('campus_events_block', 'campus_events', true);

            $prevDay = $currentDay;
        }

        //setup block for submission button
        $this->setBlock($templateHandle, "submit_event", "submit_event_block");
        if ($this->permission < WRITE_ACCESS) {
            $this->setVar("submit_event_block", "");
        } else {
            $this->parse("submit_event_block", "submit_event");
        }

        return true;
    }
}
