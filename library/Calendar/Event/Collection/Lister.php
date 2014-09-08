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
    public function __construct($api, $startTimestamp, $endTimestamp, $page, $user)
    {
        parent::__construct($api, $startTimestamp, $endTimestamp, $page);

        $this->setUser($user);
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
        $this->getPage()->setBlock('event', 'event_allday', 'event_allday_block');
        $this->getPage()->setBlock('event', 'event_timed', 'event_timed_block');

        $this->getPage()->setVar(
            array(
                'date_block' => '',
                'has_no_events_block' => ''
            )
        );

        $renderer = new Calendar_Event_Type_Renderer_Lister($this->getPage());

        $lastDate = 0;

        if ($this->getAllEvents()->count()) {
            foreach ($this->getAllEvents() as $currentEvent) {
                $currentDate = $currentEvent->getStartTime()->getDatabaseDate();

                $this->getPage()->setVar(
                    array(
                        'EVENT_DATE' => $currentEvent->getStartTime()->getDateOnly()
                    )
                );

                $currentEvent->accept($renderer);

                if ($lastDate != $currentDate) {
                    $this->getPage()->parse('date_block', 'date', true);

                    /* Clear children after parsing out the parent
                     */
                    $this->getPage()->setVar(
                        array(
                            'event_block' => ''
                        )
                    );

                    $lastDate = $currentDate;
                }
            }
        } else {
            $this->getPage()->parse('has_no_events_block', 'has_no_events');
        }

        /* Render everything else out
         */
        $groupHandler = new Group();
        $category_list = $groupHandler->involvedInCategoryList($this->getUser()->getId());

        $calendarHandler = new Calendar();
        $campus_list = $calendarHandler->getFilterCampusList($this->getUser()->getId());

        $this->getPage()->checkList(
            'filter_group',
            $category_list,
            null,
            TEMPLATE_content,
            array('name' => 'GROUP_NAME', 'id' => 'GROUP_ID')
        );
        $this->getPage()->checkList(
            'filter_campus',
            $campus_list,
            null,
            TEMPLATE_content,
            array('name' => 'CAMPUS_NAME', 'id' => 'CAMPUS_ID')
        );
        $this->getPage()->checkList(
            'filter_event_type',
            $calendarHandler->getTypeList(),
            null,
            TEMPLATE_content,
            array(
                'eventType' => 'EVENT_TYPE_NAME',
                'eventTypeID' => 'EVENT_TYPE_ID',
                'eventColour' => 'EVENT_TYPE_COLOUR'
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