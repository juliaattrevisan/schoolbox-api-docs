<?php
/**
 * Calendar_Event_Type_Submission_Renderer_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Calendar\Event\Type\Submission\Renderer;

use Calendar\Event\Type\Submission\Renderer\Base;

class Html extends Base {


    public function render()
    {
        return $this->getTemplate()->render('calendar/type/submission.twig',
            array(  'event' => $this->getEvent(),
                    'renderer' => $this)
        );
    }

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
                'TIME' => $currentEvent->getTime(),
                'FULL_TIME' => $currentEvent->getFullDate(),
                'NOTE' => $currentEvent->getTitle(),
                'COLOUR' => $currentEvent->getColour(),
                'LOCATION' => '',
                'HAS_CATEGORY_ID' => $currentEvent->getCategoryId(),
                'HAS_CATEGORY_NAME' => Scrub::text($currentEvent->getCategoryName()),
                'DETAIL' => Scrub::html($currentEvent->getSubmissionBoxDetail()),
                /* Wtf - EVENT_TYPE => 'type' . $event['type']; and TYPE => $event['eventType']
                 */
                'EVENT_TYPE' => 'submission',
                'TYPE' => $currentEvent->getEventType(),
                'AUTHOR' => Scrub::text($currentEvent->getAuthorName()),
                'AUTHOR_ID' => $currentEvent->getAuthorId(),
                'EVENT_VISIBLE' => '',
                'EVENT_LEVEL' => '',
            )
        );
        $this->getPage()->parse('has_category_block', 'has_category');
        $this->getPage()->parse('note_block', 'note', true);
    }
}
