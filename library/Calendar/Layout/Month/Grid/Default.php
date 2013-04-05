<?php
/**
 * Calendar_Layout_Month_Grid_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Layout_Month_Grid_Default extends Calendar_Layout_Month_Abstract {

    /**
     * Choose template file
     */
    protected function setupTemplateFile() {
        $this->setTemplateFile('calendar/month.html');
    }

    /**
     * Set up blocks and stuff
     */
    protected function setupBlocks() {
        $this->getPage()->setFile(TEMPLATE_content, $this->getTemplateFile());
        $this->getPage()->setTab("calendar");
        $this->getPage()->setTitle('Calendar Month');

        $this->getPage()->setBlock(TEMPLATE_content, "month_week", "month_week_listing");
        $this->getPage()->setBlock("month_week", "day", "day_block");
        $this->getPage()->setBlock("day", "note", "note_block");
        $this->getPage()->setBlock("note", "attendance_icon", "attendance_icon_block");
        $this->getPage()->setBlock("note", "attendance_status", "attendance_status_block");
        $this->getPage()->setBlock("note", "attendance_request_button", "attendance_request_button_block");
        $this->getPage()->setBlock("note", "attendance_list_button", "attendance_list_button_block");
        $this->getPage()->setBlock('note', 'has_category', 'has_category_block');

        /* Initialise selectedDay, selectedMonth, selectedYear
         * Initialise movement buttons
         */
        $this->setupSelectedDayMonthYear();
        $this->setupMovementButtons();
    }

    /**
     * Set up selected day/month/year
     */
    protected function setupSelectedDayMonthYear() {
        list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $this->getDate());

        $selectedDate = @mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear);

        $this->setSelectedDate($selectedDate);

        if ($selectedDate <= 0) {
            $selectedDate = time();
        }

        $selectedDay = date("j", $selectedDate);
        $selectedMonth = date("n", $selectedDate);
        $selectedYear = date("Y", $selectedDate);

        $dayOfWeek = date("w", mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear));
        $startOfWeek = mktime(0, 0, 0, $selectedMonth, ($selectedDay - $dayOfWeek), $selectedYear);
        $endOfWeek = strtotime("+6 days", $startOfWeek);

        $this->getPage()->setVar(
            array(
                'SELECTED_DAY' => $selectedDay,
                'SELECTED_MONTH' => $selectedMonth,
                'SELECTED_YEAR' => $selectedYear,
                'YEAR' => date("Y", $startOfWeek),
                'MONTH' => date("F", mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)),
                'DAY' => $selectedDay,
            )
        );

        $firstDay = date("w", mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));
        $numDaysInMonth = date("d", mktime(0, 0, 0, $selectedMonth + 1, 0, $this->getDate())); //
        $numWeeksMonth = ceil(($firstDay + $numDaysInMonth) / 7);
        $totalDays = $numWeeksMonth * 7 + (6 - $firstDay);

        $this->setStartDay(mktime(3, 0, 0, $selectedMonth, 1, $selectedYear) - strtotime("+$firstDay day", 0));
        $this->setFirstDay($firstDay);
        $this->setNumDaysInMonth($numDaysInMonth);
        $this->setTotalDays($totalDays);
    }

    /**
     * Render calendar
     */
    public function render() {
        $this->getPage()->setVar(
            array(
                'note_block' => ''
            )
        );

        for ($i = 0; $i < $this->getTotalDays(); $i++) {
            $currentDay = strtotime("+$i day", $this->getStartDay());

            $day = $i % 7;

            if ($i >= $this->getFirstDay() && $i < $this->getNumDaysinMonth() + $this->getFirstDay()) {
                $this->getPage()->setVar('OTHER_MONTH', '');
            } else {
                $this->getPage()->setVar('OTHER_MONTH', ' otherMonth');
            }

            $date = new Alaress_Date_Format($currentDay, 'U');

            $output = array(
                'DATE' => $date->getUsingFormat('j'),
                'ADD' => '/calendar/modify.php?date=' . date("d-m-Y", $currentDay) . '&returnTo=month',
                'ON_OFF' => !($day % 2) ? 'dark' : '',
            );

            if (date("d-m-Y", $this->getSelectedDate()) == date("d-m-Y", $currentDay)) {
                $this->getPage()->setVar("MONTABLE_CSS", "calTableSelected");
            } else {
                if (date("d-m-Y", $currentDay) == date("d-m-Y")) {
                    $this->getPage()->setVar("MONTABLE_CSS", "calTableCurrent");
                } else {
                    $this->getPage()->setVar("MONTABLE_CSS", "calTable");
                }
            }

            $this->getPage()->setVar($output);

            /* Render all registered event types
             */
            $renderer = new Calendar_Event_Collection_Renderer($this->getApi(), $currentDay, $this->getPage());
            $renderer->renderAll();

            $this->getPage()->parse("day_block", "day", true);
            $this->getPage()->setVar("note_block", "");

            if ($day == 6 && $i) {
                $this->getPage()->parse('month_week_listing', 'month_week', true);
                $this->getPage()->setVar('day_block', '');
            }
        }
    }
}
