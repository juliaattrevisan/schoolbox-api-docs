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
use \Schoolbox\Config\Loader as Config;

class Base extends RendererBase
{
    /**
     * Helper function to control showing the attendance link
     */
    public function showAttendanceLink() {
        
        if (Config::get('external_type') == 'synergetic'
            && Config::get('external_attendance')
            && $this->getViewer()->getRole()->isStaff())
        {
            $now = new \Alaress_Date_Format();
            $now->endOfDay();
            
            $from = new \Alaress_Date_Format();
            $from->addDays(Config::get('external_attendance_days') * -1);
        
            if($this->getEvent()->getStartTime()->gte($from) && $this->getEvent()->getStartTime()->lte($now)) {
                return true;
            }
        } 
        return false;
    }
} 