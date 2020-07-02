<?php
/**
 * Calendar_Layout_Full_Ajax_JSON
 *
 * This renders the JSON data, but not the page
 * The JSON array contains a set of events that lie
 * within the range specified by the 'start' and 'end'
 * querystring parameters that Fullcalendar uses
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Calendar\Event\Collection\Renderer;
use Calendar\Event\Type\EventBase;

class Calendar_Layout_Full_Ajax_JSON extends Calendar_Layout_Full_Abstract
{

    /**
     * Choose template file
     */
    protected function setupTemplateFile(): void
    {
    }

    /**
     * Set up blocks and stuff
     */
    protected function setupBlocks() :void
    {
        $this->setupSelectedDayMonthYear();
    }

    /**
     * Set up selected day/month/year
     */
    protected function setupSelectedDayMonthYear(): void
    {
        list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $this->date);

        $selectedDate = @mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear);

        /* Default url access doesn't have a date portion
         * i.e. /calendar/week/. In that case, just use now
         */
        if ($selectedDate <= 0) {
            $selectedDate = time();
        }

        $this->selectedDate = $selectedDate;

        if ($selectedDate <= 0) {
            $selectedDate = time();
        }

        $selectedDay   = date("j", $selectedDate);
        $selectedMonth = date("n", $selectedDate);
        $selectedYear  = date("Y", $selectedDate);

        $dayOfWeek   = date("w", mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear));
        $startOfWeek = mktime(0, 0, 0, $selectedMonth, ($selectedDay - $dayOfWeek), $selectedYear);

        $this->startOfWeek = $startOfWeek;
    }

    /**
     * Render calendar
     */
    public function render(): void
    {
        header('Content-type: application/json');

        $startSequence = new Alaress_Date_Format($_GET['start'], 'U');
        $endSequence   = new Alaress_Date_Format($_GET['end'], 'U');
        $renderer      = new Renderer(
            $this->api,
            $startSequence->getUnixTimeStamp(),
            $endSequence->getUnixTimeStamp(),
            $this->user,
            $this->page->getDi()->get('authorizer'),
            $this->page->getDi()->get('router'),
            $this->viewer
        );

        /* Render all registered events
         */
        echo "[" . \implode(',', $renderer->renderAll(EventBase::RENDER_FORMAT_JSON)) . "]";

        // Save page that is being viewed into history.
        // Problem with storing start of sequence:
        // month view often starts at a day in the previous month.
        // Therefore, consider setting as midpoint between two dates, or the
        // first of the actual month being shown.
        $page        = new SitePage(false);
        $midSequence = new Alaress_Date_Format((intval($_GET['start']) + intval($_GET['end'])) / 2, 'U');

        // Also store the current user in the history
        $page->saveHistory('/calendar/' . $midSequence->getURLDate() . '/' . $this->user->getId());
        exit();
    }
}
