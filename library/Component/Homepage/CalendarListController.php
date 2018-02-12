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
use \Schoolbox\Permission\Access\UserAccess;
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

    /** @var  Alaress_Date_Format $start */
    protected $start;

    /** @var  Alaress_Date_Format $end */
    protected $end;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if (!$this->isSourceRenderable() && !$this->hasContextWriteAccess()) {
            return false;
        }

        $this->db              = DeprecatedDatabase::getInstance();
        $this->calendarHandler = new Calendar();
        return true;
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
        /** @var Alaress_Date_Format|bool $prevDay */
        $prevDay     = false;
        $this->start = $now->startOfDay();
        $this->end   = clone $this->start;
        $this->end->addDays($days);

        // Produce API for schoolbox group events
        $calendarConfig = Calendar_Api_Config_Mapper::getInstance()->findOneForClass(Calendar_Api_EventType_Event_Schoolbox_Campus::class);
        $calendarApi    = new Calendar_Api_EventType_Event_Schoolbox_Campus($calendarConfig->getId(), $this->getUser());

        // Render all events for group using html renderer
        $eventRenderer = new Lister(
            $calendarApi,
            $this->start->getUnixTimeStamp(),
            $this->end->getUnixTimeStamp(),
            $this->getUser(),
            $this->getSitePage(),
            $this->getUser()
        );
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

            $htmlEvent = $eventRenderer->renderEvent($currentEvent, 'html');
            if($htmlEvent) {
                $this->setVar('campus_events', $htmlEvent);
                $this->parse('campus_events_block', 'campus_events', true);
            }


            $prevDay = $currentDay;
        }

        //setup block for submission button
        $this->setBlock($templateHandle, "submit_event", "submit_event_block");
        if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
            $this->setVar("submit_event_block", "");
        } else {
            $this->parse("submit_event_block", "submit_event");
        }

        return true;
    }
}
