<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Calendar\Event\Collection\Renderer;

class Component_Homepage_EventListController extends Component_Homepage_ControllerAbstract
{
    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'eventList')) {
            return false;
        }
        $this->parseConfig();

        //event output
        $this->setBlock($templateHandle, 'group_events', 'group_events_block');
        $this->setBlock($templateHandle, 'import_events', 'import_events_block');

        $this->setVar('CALENDAR_TITLE', 'Group Calendar');

        // Filter to a given number of days.
        $eventLimit = intval($this->getConfig('size'));
        $dayLimit   = intval($this->getConfig('days'));

        if ($dayLimit <= 0) {
            $dayLimit = 365;
        }

        $now         = Alaress_Date_Format::now();
        $this->start = $now->startOfDay();
        $this->end   = clone $this->start;
        $this->end->addDays($dayLimit);

        // Produce API for schoolbox group events
        $calendarApi  = new Calendar_Api_EventType_Event_Schoolbox_Group($_SESSION['user'], array());
        $calendarApi->setGroupId($this->getContext()->getId());

        // Render all events for group using html renderer
        $eventRenderer = new Renderer($calendarApi, $this->start->getUnixTimestamp(), $this->end->getUnixTimestamp(), $_SESSION['user'], $this->sitepage);
        $this->setVar('group_events_block', $eventRenderer->renderAll('html'));

        //setup block for submission button
        $this->setBlock($templateHandle, "submit_event", "submit_event_block");
        if ($this->permission < WRITE_ACCESS) {
            $this->setVar(array(
                    'submit_event_block' => '',
                    'import_events_block' => ''
                ));
        } else {
            $this->parse("submit_event_block", "submit_event");
            $this->parse('import_events_block', 'import_events');
        }

        return true;
    }
}
