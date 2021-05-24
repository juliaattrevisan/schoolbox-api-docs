<?php

/**
 * EventRecurrenceMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC;

use Schoolbox\Calendar;

class EventRecurrenceMapper extends Calendar\EventRecurrenceMapper
{

    /**
     * @param Event $seriesEvent
     * @param Event $coreEvent
     */
    protected function setSeriesEventData($seriesEvent, $coreEvent)
    {
        parent::setSeriesEventData($seriesEvent, $coreEvent);
        $seriesEvent->setMetadata($coreEvent->getMetadata());
    }
}
