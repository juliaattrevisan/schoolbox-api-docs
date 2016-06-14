<?php
/**
 * Calendar_Event_Type_EOTC_Renderer_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Calendar\Event\Type\EOTC\Renderer;

class Html extends Base {

    /**
     * Render this event
     *
     */
    public function render()
    {
        if ($this->isVisible()) {
            return $this->getTemplate()->render('calendar/type/EOTC.twig',
                array('event' => $this->getEvent())
            );
        } else {
            return '';
        }
    }
}
