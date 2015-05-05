<?php

/**
 * Calendar_Event_Type_Submission_Renderer_Javascript
 * Renders out a JSON mob of event objects
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Calendar\Event\Type\Submission\Renderer;

use Calendar\Event\Type\Submission\Renderer\Base;
use Schoolbox\Resource\Category;
use \Scrub;

class Javascript extends Base
{

    /**
     * Visitor
     *
     * @param $this->getEvent()
     */
    public function render()
    {
        /* Show a homepage if appropriate
         */
        $categoryId   = $this->getEvent()->getCategory()->getId();
        $categoryName = $this->getEvent()->getCategory()->getName();

        /* Congeal all that stuff
         */
        $start   = $this->getEvent()->getStartTime()->getDatabaseDate();
        $all_day = $this->getEvent()->getAllDay();
        if (!$all_day) {
            $start .= ' ' . $this->getEvent()->getStartTime()->getDatabaseTime();
        }

        $event = array(
            'title'     => $this->getEvent()->getTitle(),
            'start'     => $start,
            'end'       => "",
            /* Whether you can edit this event or not
             */
            'editable'  => $this->isEditable(),
            /* Whether it's an all-day event or not
             * If allDay is true, then you can't resize it
             *
             * Schoolbox usage would be that all events are not
             * allDay events
             */
            'allDay'    => $all_day,
            /* Colour of the event
             */
            'color'     => $this->getEvent()->getColour(),
            'className' => $this->getEvent()->getEventClasses(),
            /* Extra data that the Event Object doesn't directly support
             */
            'data'      => array(
                'meta'       => array(
                    'eventId'   => $this->getEvent()->getEventId(),
                    'time'      => $this->getEvent()->getTime(),
                    'detail'    => Scrub::html($this->getEvent()->getDetail()),
                    'location'  => '',
                    'eventType' => 'type' . $this->getEvent()->getType(),
                    'type'      => $this->getEvent()->getEventType(),
                    'author'    => $this->getEvent()->getAuthorName(),
                    'authorId'  => $this->getEvent()->getAuthorId(),
                    'level'     => $this->getEvent()->getEventClasses(),
                    'completed' => '',
                ),
                'links'      => array(
                    'category' => array(
                        'id'   => $categoryId,
                        'name' => $categoryName,
                    )
                ),
                'styles'     => array(
                    'visible' => true,
                ),
                'attendance' => array(
                    'icon'          => false,
                    'status'        => false,
                    'requestButton' => false,
                    'listButton'    => false,
                ),
                'custom' => array(
                    'modifyLink' => '/submission/createSubmission/modify/' . $this->getEvent()->getEventId(),
                )
            )
        );

        return json_encode($event).',';
    }
}
