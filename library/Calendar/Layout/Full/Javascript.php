<?php

/**
 * Calendar_Layout_Full_Javascript
 *
 * This renders the page in which the calendar will live,
 * but not the data. This separates responsibilities even
 * though it seems like excessive amount of class usage.
 *
 * The javascript calendar is all self contained.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Layout_Full_Javascript extends Calendar_Layout_Full_Abstract
{
    /**
     * Choose template file
     */
    protected function setupTemplateFile()
    {
        $this->setTemplateFile('calendar/full.html');
        $this->getPage()->setTab('calendar');
    }

    /**
     * Set up blocks and stuff
     */
    protected function setupBlocks()
    {
        $this->getPage()->setFile(TEMPLATE_content, $this->getTemplateFile());
        $this->getPage()->setBlock(TEMPLATE_content, 'event_type', 'event_type_block');

        $this->getPage()->setVar(
            array(
                'USER_CALENDAR_ID' => $this->getUser()->getId(),
                'USER_FULLNAME'    => $this->getUser()->getFullname(),
            )
        );

        $this->setupSelectedDayMonthYear();
    }

    /**
     * Set up selected day/month/year
     */
    protected function setupSelectedDayMonthYear()
    {
        list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $this->getDate());

        $selectedDate = @mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear);

        $this->setSelectedDate($selectedDate);

        if ($selectedDate <= 0) {
            $selectedDate = time();
        }

        $selectedDay = date("j", $selectedDate);
        $selectedMonth = date("n", $selectedDate);
        $selectedYear = date("Y", $selectedDate);

        $this->getPage()->setVar(
            array(
                'SELECTED_DAY'   => $selectedDay,
                'SELECTED_MONTH' => $selectedMonth - 1,
                'SELECTED_YEAR'  => $selectedYear,
                'SELECTED_DATE'  => date('Y-m-d', $selectedDate),
            )
        );
    }

    /**
     * Render calendar
     */
    public function render()
    {
        $this->setupMovementButtons();

        $this->getPage()->setVar(
            array(
                'CALENDAR_USER_ID'         => $this->getUser()->getId(),
                'CALENDAR_VIEW'            => $_SESSION['calendar_view'] ? $_SESSION['calendar_view'] : 'month',
                'CALENDAR_COLLAPSE_EVENTS' => Config::get('calendar_collapse_events') ? 1 : '',
                'CALENDAR_START_DAY'       => Config::get('calendar_start_day') ?: 0,
                'USER_TOKEN'               => $this->getUser()->getKey(),
            )
        );

        /* Render event types to choose from
         */
        /** @var \Schoolbox\Calendar\EventType[] $allTypes */
        $allTypes = \Schoolbox\Calendar\EventTypeMapper::getInstance()->findAll();

        if ($allTypes->count()) {
            foreach ($allTypes as $currentType) {
                $this->getPage()->setVar(
                    array(
                        'EVENT_TYPE_ID'   => $currentType->getId(),
                        'EVENT_TYPE_NAME' => Scrub::text($currentType->getType()),
                    )
                );

                $this->getPage()->parse('event_type_block', 'event_type', true);

                /* Event Task id
                 */
                if ($currentType->isTaskType()) {
                    $this->getPage()->setVar(
                        array(
                            'TASK_TYPE_ID' => $currentType->getId(),
                        )
                    );
                }
            }
        } else {
            $this->getPage()->setVar('event_type_block', '');
        }
    }
}
