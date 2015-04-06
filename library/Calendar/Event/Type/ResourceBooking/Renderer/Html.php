<?php
/**
 * Calendar_Event_Type_EOTC_Renderer_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Calendar\Event\Type\ResourceBooking\Renderer;

use Calendar\Event\Type\RendererBase;

class Html extends RendererBase {
    /**
     * Render this event
     *
     */
    public function render()
    {

        if ($this->isVisible()) {
            return $this->getTemplate()->render('calendar/type/resourceBooking.twig',
                array('event' => $this->getEvent())
            );
        } else {
            return '';
        }

    }
}
