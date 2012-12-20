<?php
/**
 * Calendar_Event_Type_StudentBirthday_Renderer_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Event_Type_StudentBirthday_Renderer_Default extends Calendar_Event_Type_Renderer_Abstract {

    /**
     * Render this event
     *
     * @param $currentEvent
     */
    public function visit($currentEvent) {

        /* Clear attendance blocks
         */
        $this->getPage()->setVar(
            array(
                'attendance_icon_block' => '',
                'attendance_status_block' => '',
                'attendance_request_button_block' => '',
                'attendance_list_button_block' => '',
                'has_category_block' => '',
            )
        );

        $this->getPage()->setVar(
            array(
                'EVENT_ID' => '',
                'TIME' => '',
                'FULL_TIME' => '',
                'NOTE' => $currentEvent->getNote(),
                'COLOUR' => $currentEvent->getColour(),
                'LOCATION' => '',
                'DETAIL' => '',
                /* Wtf - EVENT_TYPE => 'type' . $event['type']; and TYPE => $event['eventType']
                 */
                'EVENT_TYPE' => 'type' . $currentEvent->getType(),
                'TYPE' => $currentEvent->getEventType(),
                'AUTHOR' => '',
                'AUTHOR_ID' => '',
                'EVENT_VISIBLE' => '',
                'EVENT_LEVEL' => '',
            )
        );

        $this->getPage()->parse("note_block", "note", true);
    }
}
