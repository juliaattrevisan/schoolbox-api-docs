<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Calendar\Event\Collection\Lister;

class Component_Homepage_EventListController extends Component_Homepage_ControllerAbstract
{

    /**
     * Component name
     *
     * @var string
     */
    protected $componentName = 'Group Calendar';

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
        $this->setBlock($templateHandle, 'event_day', 'event_day_block');
        $this->setBlock('event_day', 'group_events', 'group_events_block');
        $this->setBlock($templateHandle, 'import_events', 'import_events_block');

        $this->setVar(
            array(
                'CALENDAR_PREFIX' => 'group',
                'USER_TOKEN'      => $_SESSION['key'],
                'LIST_DATE'       => '',
                'group_events_block'=> '',
            )
        );

        // Filter to a given number of days.
        $eventLimit = intval($this->getConfig('size'));
        $dayLimit   = intval($this->getConfig('days'));

        if ($dayLimit <= 0) {
            $dayLimit = 365;
        }

        $now         = Alaress_Date_Format::now();
        $prevDay = false;
        $this->start = $now->startOfDay();
        $this->end   = clone $this->start;
        $this->end->addDays($dayLimit);

        // Produce API for schoolbox group events
        $calendarApi  = new Calendar_Api_EventType_Event_Schoolbox_Group($_SESSION['user'], array());
        $calendarApi->setGroupId($this->getContext()->getId());

        $eventCount = 0;

        // Render all events for group using html renderer
        $eventRenderer = new Lister($calendarApi, $this->start->getUnixTimestamp(), $this->end->getUnixTimestamp(), $_SESSION['user'], $this->sitepage);
        foreach ($eventRenderer->getAllEvents() as $currentEvent) {

            if($eventLimit > 0 && $eventCount++ >= $eventLimit) {
                break;
            }

            //
            // Current day: will never be before the start time
            $currentDay = $currentEvent->getStartTime();
            if ($this->start->compare($currentDay) > 0) {
                $currentDay = $this->start;
            }
            if ($prevDay != false && $currentDay->getDateOnly() != $prevDay->getDateOnly()) {
                $this->parse('event_day_block', 'event_day', true);
                $this->setVar('group_events_block', '');
            }

            $this->setVar('LIST_DATE', $currentDay->getLongDateOnly());

            $this->setVar('group_events', $eventRenderer->renderEvent($currentEvent, 'html'));

            $this->parse('group_events_block', 'group_events', true);

            $prevDay = $currentDay;
        }


        $this->parse('event_day_block', 'event_day', true);


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
