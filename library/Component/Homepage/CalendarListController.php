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

use Calendar\Event\Collection\Lister;

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

        $this->setVar(array(
            'LIST_DATE' => '',
        ));

        if($this->getConfig('size') > 0) {
            $days = $this->getConfig('size');
        }
        else {
            $days = 160;
        }

        $now         = Alaress_Date_Format::now();
        $prevDay = false;
        $this->start = $now->startOfDay();
        $this->end   = clone $this->start;
        $this->end->addDays($days);

        // Produce API for schoolbox group events
        $calendarApi  = new Calendar_Api_EventType_Event_Schoolbox_Campus($_SESSION['user'], array());

        // Render all events for group using html renderer
        $eventRenderer = new Lister($calendarApi, $this->start->getUnixTimestamp(), $this->end->getUnixTimestamp(), $_SESSION['user'], $this->sitepage);
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

            $this->setVar('LIST_DATE', $currentDay->getLongDateOnly());

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
