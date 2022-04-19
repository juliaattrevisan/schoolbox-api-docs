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
     * @throws InvalidArgumentException
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
            $this->authorizer,
            $this->router,
            $this->viewer,
            $this->allowedEventTypes
        );

        /* Render all registered events
         */
        echo '[' . \implode(',', $renderer->renderAll(EventBase::RENDER_FORMAT_JSON)) . ']';

        exit();
    }
}
