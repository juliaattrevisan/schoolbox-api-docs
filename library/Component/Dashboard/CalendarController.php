<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 *
 */

use Calendar\Event\Collection\Lister;

class Component_Dashboard_CalendarController extends Component_Dashboard_ControllerAbstract
{

    protected $calendar;

    protected $group;

    /**
     * @var Alaress_Date_Format
     */
    protected $start;

    /**
     * @var Alaress_Date_Format
     */
    protected $end;

    /**
     * @var Lister
     */
    protected $eventRenderer;

    public function __construct(Component_Instance $instance, \Schoolbox\User $user, SitePage $sitepage)
    {
        parent::__construct($instance, $user, $sitepage);

        $this->setCalendar(new Calendar());

        $this->setGroup(new Group());
    }

    /**
     * Get all the events you need to pump into the page
     *
     * @return bool|void
     */
    public function execute()
    {
        $now         = Alaress_Date_Format::now();
        $this->start = $now->startOfDay();
        $this->end   = clone $this->start;
        $days        = $this->getConfig('days');

        /* Get 30 days of events by default
         */
        if ($days <= 0) {
            $days = 30;
        }

        $this->end->addDays($days);

        /* A list of submissions
         */
        $eventFactory = new Calendar_Api_Factory($this->user);
        $api = $eventFactory->produce();
        $this->eventRenderer = new Lister($api, $this->start->getUnixTimestamp(), $this->end->getUnixTimestamp(), $this->user, $this->sitepage);

        return true;
    }

    public function renderTemplate($templateHandle)
    {

        if (!$this->loadTemplate($templateHandle, 'calendar')) {
            return false;
        }

        $this->setBlock($templateHandle, 'day', 'day_block');
        $this->setBlock('day', 'has_events', 'has_events_block');
        $this->setBlock('has_events', 'event', 'event_block');
        $this->setBlock('day', 'no_events', 'no_events_block');
        $this->setVar('','');

        $this->setVar(array(
            'day_block'        => '',
            'has_events_block' => '',
            'no_events_block'  => '',
            'event_block'      => '',

            'LIST_DATE'        => $this->start->getLongDateOnly(),
            'DATE'             => $this->start->getLongDateOnly(),
            'CALENDAR_DATE'    => $this->start->getURLDate(),
        ));

        /** @var Alaress_Date_Format $prevDay */
        $prevDay = false;

        if (count($this->eventRenderer->getAllEvents()) === 0) {
            $this->parse('no_events_block', 'no_events');
        }
        else {
            foreach ($this->eventRenderer->getAllEvents() as $currentEvent) {

                //
                // Current day: will never be before the start time
                $currentDay = $currentEvent->getStartTime();
                if ($this->start->compare($currentDay) > 0) {
                    $currentDay = $this->start;
                }

                if ($prevDay != false && $currentDay->getDateOnly() != $prevDay->getDateOnly()) {
                    $this->parse('has_events_block', 'has_events');
                    $this->parse('day_block', 'day', true);

                    /* Clear children after parsing out the parent
                     */
                    $this->setVar('LIST_DATE', $currentDay->getLongDateOnly());
                    $this->setVar('event_block', '');
                }

                $this->setVar('EVENT_CONTENT', $this->eventRenderer->renderEvent($currentEvent, 'html'));

                $this->parse("event_block", "event", true);

                $prevDay = $currentDay;
            }

            $this->parse('has_events_block', 'has_events');
        }

        $this->parse('day_block', 'day', true);

        return true;
    }

    public function setCalendar($calendar)
    {
        $this->calendar = $calendar;
    }

    public function getCalendar()
    {
        return $this->calendar;
    }

    public function setGroup($group)
    {
        $this->group = $group;
    }

    public function getGroup()
    {
        return $this->group;
    }
}
