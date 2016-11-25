<?php
/**
 * Calendar_Event_Type_Day_Renderer_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Calendar\Event\Type\Day\Renderer;

class Html extends Base {


    /**
     * Render this event as html using twig
     * @return string
     */
    public function render()
    {
        if ($this->isVisible()) {
            return $this->getTemplate()->render('calendar/type/day.twig',
                array(  'event' => $this->getEvent(),
                        'renderer' => $this,
                        'returnTo' => urlencode($_SERVER["REQUEST_URI"])
                )
            );
        } else {
            return '';
        }

    }
}
