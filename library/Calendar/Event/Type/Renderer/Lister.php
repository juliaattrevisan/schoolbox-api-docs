<?php
/**
 * Lister
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Calendar_Event_Type_Renderer_Lister extends Calendar_Event_Type_Renderer_Abstract {
    /**
     * Render this event
     *
     * @param Calendar_Event_Type_Abstract $currentEvent
     * @return Sabre\VObject\Component\Vevent
     */
    public function visit($currentEvent)
    {
        $this->getPage()->setVar(array(
            'EVENT_START' => $currentEvent->getStartTime()->getTimeOnly(),
            'EVENT_END' => $currentEvent->getEndTime()->getTimeOnly(),
            'EVENT_TITLE' => $currentEvent->getTitle(),
            'EVENT_TYPE' => $currentEvent->getType(),
            'EVENT_COLOUR' => $currentEvent->getColour(),
        ));

        $this->getPage()->parse('event_block', 'event', true);
    }
} 