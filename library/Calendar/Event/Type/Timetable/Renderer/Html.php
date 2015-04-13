<?php
/**
 * Calendar_Event_Type_Day_Renderer_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Calendar\Event\Type\Timetable\Renderer;

class Html extends Base {


    /**
     * Render this event as html using twig
     *
     */
    public function render()
    {

        if ($this->isVisible()) {
            return $this->getTemplate()->render('calendar/type/timetable.twig',
                array(  'event' => $this->getEvent(),
                        'renderer' => $this)
            );
        } else {
            return '';
        }

    }
}
