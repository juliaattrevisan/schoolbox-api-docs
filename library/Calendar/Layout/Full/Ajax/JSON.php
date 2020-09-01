<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use Calendar\Event\Collection\Renderer;
use Calendar\Event\Type\EventBase;

/**
 * Class Calendar_Layout_Full_Ajax_JSON
 *
 * This renders the JSON data, but not the page
 * The JSON array contains a set of events that lie
 * within the range specified by the 'start' and 'end'
 * querystring parameters that Fullcalendar uses
 */
class Calendar_Layout_Full_Ajax_JSON extends Calendar_Layout_Full_Abstract
{
    /**
     * Render calendar
     *
     * @param bool $saveHistory
     * @throws InvalidArgumentException
     */
    public function render(bool $saveHistory = false): void
    {
        header('Content-type: application/json');

        $startSequence = new Alaress_Date_Format($_GET['start'], 'U');
        $endSequence   = new Alaress_Date_Format($_GET['end'], 'U');
        $renderer      = new Renderer(
            $this->api,
            $startSequence->getUnixTimeStamp(),
            $endSequence->getUnixTimeStamp(),
            $this->user,
            $this->authorizer,
            $this->router,
            $this->viewer
        );

        /* Render all registered events
         */
        echo '[' . \implode(',', $renderer->renderAll(EventBase::RENDER_FORMAT_JSON)) . ']';

        // Save page that is being viewed into history.
        // Problem with storing start of sequence:
        // month view often starts at a day in the previous month.
        // Therefore, consider setting as midpoint between two dates, or the
        // first of the actual month being shown.
        $page        = new SitePage(false);
        $midSequence = new Alaress_Date_Format((intval($_GET['start']) + intval($_GET['end'])) / 2, 'U');

        /* When you view another person's calendar and try to manage events;
         *
         * e.g. such as staff viewing a student's calendar then adding an event for them,
         * redirect back to the student's calendar
         *
         * But you should only do this in some places, such as /calendar, where you can
         * actually manage someone else's calendar.
         *
         * Viewing a homepage calendar component shouldn't add to the history, nor the dashboard
         * component; both of which use this method to generate data
         */
        if ($saveHistory) {
            // Also store the current user in the history
            $page->saveHistory('/calendar/' . $midSequence->getURLDate() . '/' . $this->user->getId());
        }

        exit();
    }
}
