<?php

/**
 * Lister
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Event_Collection_Lister extends Calendar_Event_Collection_Renderer
{

    /**
     * User
     *
     * @var \Schoolbox\User
     */
    private $user = null;

    /**
     * Constructor
     *
     * @param $api
     * @param $startTimestamp
     * @param $endTimestamp
     * @param $page
     * @param $user
     */
    public function __construct($api, $startTimestamp, $endTimestamp, $page, \Schoolbox\User $user)
    {
        parent::__construct($api, $startTimestamp, $endTimestamp, $page);

        $this->setUser($user);

        /* Detonate multi-day events into their individual day ingredients
         * Merje and sort
         */
        $this->setAllEvents($this->detonate($this->getAllEvents()));
        $this->getAllEvents()->sort();
    }

    /**
     * Find out if there are multi-day events which also occur
     * on this day - if there are, render out an all-day instance
     * if this isn't its end day. Do this by exploding multi-day
     * events into its constituent parts, due to edge cases that
     * occur in the foreach()
     *
     * e.g. only one multi-day event
     *
     * Turned out to be easier to simply clone and explode, then
     * re-sort on date. This is a detonate-and-merge.
     */
    private function detonate() {
        $collection = new \Calendar_Event_Collection();

        foreach ($this->getAllEvents() as $currentEvent) {

            /* Same-day event, just throw it into the pile
             */
            if ($currentEvent->getStartTime()->getDatabaseDate() == $currentEvent->getEndTime()->getDatabaseDate()) {
                $collection->add($currentEvent);
            }
            /* Otherwise, multi-day event - detonate it
             */
            else {
                /* Alter the end date of the first instance, so it doesn't
                 * display the end time at the start of the span
                 */
                /* Special note to Renderer class
                 */
                $currentEvent->setCustom(array(
                    'hideEnd' => true
                ));

                $collection->add($currentEvent);

                /* Independence Day!
                 * Free clones from their imperial counterparts
                 */
                $clone = clone($currentEvent);
                $cloneStart = new \Alaress_Date_Format($currentEvent->getStartTime()->getUnixTimestamp(), 'U');
                $clone->setStartTime($cloneStart);

                /* Add each detonated instance into collection
                 */
                while ($clone->getStartTime()->addDays(1)->getDatabaseDate() < $currentEvent->getEndTime()->getDatabaseDate()) {

                    /* Set all-day flag for the clones
                     */
                    $clone->setAllDay(true);
                    $collection->add($clone);

                    /* Self replicate, self independence
                     */
                    $clone = clone($clone);
                    $clone->setStartTime(new \Alaress_Date_Format($clone->getStartTime()->getUnixTimestamp(), 'U'));
                }

                /* This is the day on which start matches end.
                 * Display datetime in a special way
                 */
                $startDate = $clone->getStartTime()->getDatabaseDate();
                $startTime = $currentEvent->getStartTime()->getDatabaseTime();

                $clone = clone($clone);

                /* Reset AllDay status conditionally
                 */
                if (!$currentEvent->getAllDay()) {
                    $clone->setAllDay(false);
                }

                $clone->setStartTime(new \Alaress_Date_Format("{$startDate} {$startTime}", 'y-m-d h:i:s'));

                /* Special note to Renderer class
                 */
                $clone->setCustom(array(
                    'hideStart' => true
                ));

                $collection->add($clone);
            }
        }

        return $collection;
    }

    /**
     * Render all the events in a list format
     */
    public function renderAll()
    {
        $this->getPage()->setFile(TEMPLATE_content, 'calendar/list.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'has_no_events', 'has_no_events_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'date', 'date_block');
        $this->getPage()->setBlock('date', 'event', 'event_block');
        $this->getPage()->setBlock('event', 'event_location', 'event_location_block');
        $this->getPage()->setBlock('event', 'event_allday', 'event_allday_block');
        $this->getPage()->setBlock('event', 'event_timed', 'event_timed_block');
        $this->getPage()->setBlock('event', 'event_submission', 'event_submission_block');
        // More blocks for attendance and that
        $this->getPage()->setBlock('event', 'event_has_category', 'event_has_category_block');
        $this->getPage()->setBlock('event', 'event_buttons', 'event_buttons_block');
        $this->getPage()->setBlock('event', 'event_attendance_status', 'event_attendance_status_block');
        $this->getPage()->setBlock('event', 'event_attendance_request', 'event_attendance_request_block');

        $this->getPage()->setVar(
            array(
                'date_block'          => '',
                'has_no_events_block' => ''
            )
        );

        $renderer = new Calendar_Event_Type_Renderer_Lister($this->getPage());

        $lastDate = false;

        if ($this->getAllEvents()->count()) {
            foreach ($this->getAllEvents() as $currentEvent) {
                $currentDate = $currentEvent->getStartTime()->getDatabaseDate();

                if ($lastDate && ($lastDate != $currentDate)) {
                    $this->getPage()->parse('date_block', 'date', true);

                    /* Clear children after parsing out the parent
                     */
                    $this->getPage()->setVar(
                        array(
                            'event_block' => ''
                        )
                    );
                }

                $this->getPage()->setVar(
                    array(
                        'EVENT_DATE' => $currentEvent->getStartTime()->getLongDateOnly()
                    )
                );

                $currentEvent->accept($renderer);

                $lastDate = $currentDate;
            }
            // Parze out blox after last event
            $this->getPage()->parse('date_block', 'date', true);
        } else {
            $this->getPage()->parse('has_no_events_block', 'has_no_events');
        }


        $groupHandler  = new Group();
        $category_list = $groupHandler->involvedInCategoryList($this->getUser()->getId());

        $calendarHandler = new Calendar();
        $campus_list     = $calendarHandler->getFilterCampusList($this->getUser()->getId());

        /* Render everything else out
         */
        // Load sources
        $sourceList = Calendar_Api_Config_Mapper::getInstance()->getSourceListForCalendar();
        // select all types
        $typeList = \Schoolbox\Calendar\EventTypeMapper::getInstance()->findAll();
        if (isset($_SESSION['calendarFilter'])) {
            $selected['group']      = $_SESSION['calendarFilter']['groups'];
            $selected['campus']     = $_SESSION['calendarFilter']['campus'];
            $selected['event_type'] = $_SESSION['calendarFilter']['events'];
            $selected['source']     = $_SESSION['calendarFilter']['sources'];
        } else {
            // select all groups
            if (count($category_list)) {
                foreach ($category_list as $cat) {
                    $selected['group'][] = $cat['id'];
                }
            }

            // select all campuses
            $selected['campus'] = $_SESSION['campus'];

            foreach ($typeList as $type) {
                $selected['event_type'][] = $type->getId();
            }

            // select all sources
            foreach ($sourceList as $source) {
                $selected['source'][] = $source['id'];
            }
        }

        $this->getPage()->dropDown(
            'export_group',
            $category_list,
            array(),
            TEMPLATE_content,
            array('name' => 'EXPORT_GROUP_NAME', 'id' => 'EXPORT_GROUP_ID')
        );
        $this->getPage()->dropDown(
            'export_campus',
            $campus_list,
            array(),
            TEMPLATE_content,
            array('CAMPUS_NAME' => 'EXPORT_CAMPUS_NAME', 'CAMPUS_ID' => 'EXPORT_CAMPUS_ID')
        );

        $this->getPage()->checkList(
            'filter_group',
            $category_list,
            $selected['group'],
            TEMPLATE_content,
            array('name' => 'GROUP_NAME', 'id' => 'GROUP_ID')
        );
        // Might need special PHPLibs here
        $this->getPage()->checkList(
            'filter_source',
            $sourceList,
            $selected['source'],
            TEMPLATE_content,
            array('name' => 'SOURCE_NAME', 'id' => 'SOURCE_ID')
        );
        $this->getPage()->checkList(
            'filter_campus',
            $campus_list,
            $selected['campus'],
            TEMPLATE_content,
            array('name' => 'CAMPUS_NAME', 'id' => 'CAMPUS_ID')
        );
        $this->getPage()->checkList(
            'filter_event_type',
            $typeList,
            $selected['event_type'],
            TEMPLATE_content,
            array(
                'type'  => 'EVENT_TYPE_NAME',
                'id'    => 'EVENT_TYPE_ID',
                'color' => 'EVENT_TYPE_COLOUR'
            )
        );

        $this->getPage()->setVar(
            array(
                'USER_FULLNAME' => $this->getUser()->getFullname()
            )
        );
    }

    /* Getters/Setters
     */

    /**
     * Set user
     *
     * @param \Schoolbox\User $user
     */
    private function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * Get user
     *
     * @return \Schoolbox\User
     */
    private function getUser()
    {
        return $this->user;
    }
}
