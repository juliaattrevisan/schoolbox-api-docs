<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Calendar\Event\Collection\Lister;
use Calendar\Event\Type\EventBase;
use \Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_EventListController extends Component_Homepage_ControllerAbstract
{

    /** @var  Alaress_Date_Format $start */
    protected $start;

    /** @var  Alaress_Date_Format $end */
    protected $end;

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

        // Filter to a given number of days.
        $eventLimit = intval($this->getConfig('size'));
        $dayLimit   = intval($this->getConfig('days'));

        if ($dayLimit <= 0) {
            $dayLimit = 365;
        }

        $now         = Alaress_Date_Format::now();
        /** @var Alaress_Date_Format $prevDay */
        $prevDay     = false;
        $this->start = $now->startOfDay();
        $this->end   = clone $this->start;
        $this->end->addDays($dayLimit);

        // Produce API for schoolbox group events
        $calendarConfig = Calendar_Api_Config_Mapper::getInstance()->findOneForClass(Calendar_Api_EventType_Event_Schoolbox_Group::class);
        $calendarApi    = new Calendar_Api_EventType_Event_Schoolbox_Group($calendarConfig->getId(), $this->getUser(), $this->getUser(), $this->getContext()->getId());

        // Render all events for group using html renderer
        $eventRenderer = new Lister(
            $calendarApi,
            $this->start->getUnixTimeStamp(),
            $this->end->getUnixTimeStamp(),
            $this->getUser(),
            $this->getSitePage()->getDi(),
            $this->getUser()
        );

        if ($eventRenderer->getAllEvents()->count() == 0) {

            // No events, show empty state

            $this->setBlock($templateHandle, 'event_list', 'event_list_block');
            $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');
            $this->setBlock('empty_state', 'empty_read', 'empty_read_block');
            $this->setBlock('empty_state', 'empty_write', 'empty_write_block');

            $this->setVar([
                'event_list_block' => '',
                'empty_read_block' => '',
                'empty_write_block' => '',
            ]);

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->parse('empty_read_block', 'empty_read');
            } else {
                $this->parse('empty_write_block', 'empty_write');
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {

            // List events

            $this->setBlock($templateHandle, 'event_list', 'event_list_block');
            $this->setBlock('event_list', 'event_day', 'event_day_block');
            $this->setBlock('event_day', 'group_events', 'group_events_block');
            $this->setBlock('event_list', 'import_events', 'import_events_block');
            $this->setBlock('event_list', 'submit_event', 'submit_event_block');
            $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');
            $this->setBlock($templateHandle, 'has_series', 'has_series_block');

            $this->setVar('empty_state_block', '');

            $this->setVar(
                array(
                    'CALENDAR_PREFIX' => 'group',
                    'USER_TOKEN'      => $_SESSION['key'],
                    'LIST_DATE'       => '',
                    'group_events_block'=> '',
                    'empty_state_block' => '',
                    'has_series_block' => ''
                )
            );

            $eventCount = 0;

            $hasSeries = false;
            foreach ($eventRenderer->getAllEvents() as $currentEvent) {

                if ($eventLimit > 0 && $eventCount++ >= $eventLimit) {
                    break;
                }

                // Current day: will never be before the start time

                $currentDay = $currentEvent->getStartTime();

                if ($this->start->compare($currentDay) > 0) {
                    $currentDay = $this->start;
                }

                if ($prevDay != false && $currentDay->getDateOnly() != $prevDay->getDateOnly()) {
                    $this->parse('event_day_block', 'event_day', true);
                    $this->setVar('group_events_block', '');
                }

                if ($currentEvent->getSeriesId()) {
                    $hasSeries = true;
                }

                $this->setVar('LIST_DATE', $currentDay->getLongDateOnly());

                $returnStr = null;
                $context   = $this->getContext();
                if ($context instanceof Schoolbox\Resource\Folder\Model) {
                    $returnStr = "/homepage/{$this->getContext()->getId()}";
                }
                $htmlEvent = $eventRenderer->renderEvent($currentEvent, EventBase::RENDER_FORMAT_HTML, $returnStr);
                if  ($htmlEvent) {
                    $this->setVar('group_events', $htmlEvent);
                    $this->parse('group_events_block', 'group_events', true);
                }
                $prevDay = $currentDay;
            }

            $this->parse('event_day_block', 'event_day', true);

            $this->parse('event_list_block', 'event_list', true);

            if ($hasSeries) {
                $this->parse('has_series_block', 'has_series', true);
            }

            //setup block for submission button
            $this->setBlock("event_list", "submit_event", "submit_event_block");
            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->setVar(array(
                    'submit_event_block' => '',
                    'import_events_block' => ''
                ));
            } else {
                $this->parse("submit_event_block", "submit_event");
                $this->parse('import_events_block', 'import_events');
            }
        }

        return true;
    }
}
