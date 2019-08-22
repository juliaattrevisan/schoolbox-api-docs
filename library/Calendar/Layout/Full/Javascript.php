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
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use EOTC\ConfigMapper as EOTCConfigMapper;
use Schoolbox\Calendar\Util\ViewNameMapping;

class Calendar_Layout_Full_Javascript extends Calendar_Layout_Full_Abstract
{
    /**
     * Choose template file
     */
    protected function setupTemplateFile(): void
    {
        $this->templateFile = 'calendar/full.html';
        $this->page->setTab('calendar');
    }

    /**
     * Set up blocks and stuff
     */
    protected function setupBlocks(): void
    {
        $this->page->setFile(TEMPLATE_content, $this->templateFile);
        $this->page->setBlock(TEMPLATE_content, 'event_type', 'event_type_block');
        $this->page->setBlock(TEMPLATE_content, 'has_eotc_events_all', 'has_eotc_events_all_block');

        $this->page->setVar([
            'USER_CALENDAR_ID' => $this->user->getId(),
            'USER_FULLNAME'    => $this->user->getFullname(),
        ]);

        $this->setupSelectedDayMonthYear();
    }

    /**
     * Set up selected day/month/year
     */
    protected function setupSelectedDayMonthYear(): void
    {
        list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $this->date);
        $selectedDate = @mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear);
        $this->selectedDate = $selectedDate;

        if ($selectedDate <= 0) {
            $selectedDate = time();
        }

        $selectedDay   = date("j", $selectedDate);
        $selectedMonth = date("n", $selectedDate);
        $selectedYear  = date("Y", $selectedDate);

        $this->page->setVar([
            'SELECTED_DAY'   => $selectedDay,
            'SELECTED_MONTH' => $selectedMonth - 1,
            'SELECTED_YEAR'  => $selectedYear,
            'SELECTED_DATE'  => date('Y-m-d', $selectedDate),
        ]);
    }

    /**
     * Render calendar
     */
    public function render(): void
    {
        $this->setupMovementButtons();

        /* If EOTC is enabled, show these options
         */
        if (EOTCConfigMapper::getInstance()->isSubscriptionEnabled()) {
            $this->page->parse('has_eotc_events_all_block', 'has_eotc_events_all');
        } else {
            $this->page->setVar([
                'has_eotc_events_all_block' => '',
            ]);
        }

        // Translate old calendar view to new one
        $calendarView = ViewNameMapping::MAP_STORED_TO_FC[$this->viewer->getCalendarView()]
            ?? ViewNameMapping::VIEW_MONTH_FC_NAME;

        $this->page->setVar([
            'CALENDAR_USER_ID'         => $this->user->getId(),
            'CALENDAR_VIEW'            => $calendarView,
            'CALENDAR_COLLAPSE_EVENTS' => Config::get('calendar_collapse_events') ? 1 : '',
            'CALENDAR_START_DAY'       => Config::get('calendar_start_day') ?: 0,
            'USER_TOKEN'               => $this->user->getKey(),
        ]);
    }
}
