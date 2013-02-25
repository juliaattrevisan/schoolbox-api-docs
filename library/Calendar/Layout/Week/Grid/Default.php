<?php
/**
 * Calendar_Layout_Week_Grid_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Layout_Week_Grid_Default extends Calendar_Layout_Week_Abstract {

    /**
     * Choose template file
     */
    protected function setupTemplateFile() {
        $this->setTemplateFile('calendar/week.html');
    }

    /**
     * Set up blocks and stuff
     */
    protected function setupBlocks() {
        $this->getPage()->setFile(TEMPLATE_content, $this->getTemplateFile());
        $this->getPage()->setTab("calendar");
        $this->getPage()->setTitle('Calendar Week');

        $this->getPage()->setBlock(TEMPLATE_content, "day_header", "day_header_block");
        $this->getPage()->setBlock(TEMPLATE_content, "day", "day_block");
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
     * Render calendar
     */
    public function render() {
        for ($i = 0; $i < 7; $i++) {
            $currentDay = strtotime("+$i day", $this->getStartOfWeek());

            $this->getPage()->setVar('DATE', date("j", $currentDay));
            $this->getPage()->setVar(
                'ADD', '/calendar/modify.php?date=' . date("d-m-Y", $currentDay) . '&returnTo=week'
            );
            $this->getPage()->parse('day_header_block', 'day_header', true);
        }

        for ($i = 0; $i < 7; $i++) {

            // output submission events
            $currentDay = strtotime("+$i day", $this->getStartOfWeek());

            //output selected classes
            if (date("d-m-Y", $this->getSelectedDate()) == date("d-m-Y", $currentDay)) {
                $this->getPage()->setVar("MONTABLE_CSS", "calTableSelected");
            } else {
                if (date("d-m-Y", $currentDay) == date("d-m-Y")) {
                    $this->getPage()->setVar("MONTABLE_CSS", "calTableCurrent");
                } else {
                    $this->getPage()->setVar("MONTABLE_CSS", "calTable");
                }
            }

            /* Render all registered event types
             */
            $renderer = new Calendar_Event_Collection_Renderer($this->getApi(), $currentDay, $this->getPage());
            $renderer->renderAll();

            $this->getPage()->parse("day_block", "day", true);
            $this->getPage()->setVar("note_block", "");
        }
    }
}
