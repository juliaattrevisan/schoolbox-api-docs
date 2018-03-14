<?php
/**
 * This is the base renderer for day events,
 * it provides support functions for determining how to render a day event
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

namespace Calendar\Event\Type\Timetable\Renderer;

use \Calendar\Event\Type\RendererBase;
use \Calendar\Event\Type\Timetable\Event;
use \Schoolbox\Config\Loader as Config;

abstract class Base extends RendererBase
{

    /**
     * @return bool
     */
    public function isVisible()
    {
        return true;
    }

    /**
     * Helper function to control showing the attendance link
     */
    public function showAttendanceLink() {

        $config = new Config();
        if (
            $this->getEvent() instanceof Event
            && $config->get('external_type') == 'synergetic'
            && $config->get('external_attendance')
            && $this->getEvent()->hasAttendance()
            && $this->getViewer()->getRole()->isStaff()
            && $this->getEvent()->getCode() != ''
        )
        {
            $now = new \Alaress_Date_Format();
            $now->endOfDay();

            $from = new \Alaress_Date_Format();
            $from->addDays($config->get('external_attendance_days') * -1);

            if($this->getEvent()->getStartTime()->gte($from) && $this->getEvent()->getStartTime()->lte($now)) {
                return true;
            }
        }
        return false;
    }
}
