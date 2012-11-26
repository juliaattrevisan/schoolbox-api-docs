<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Facade_Component_SchoolEvents extends Facade_Component_Generic {

    /**
     * This should return an array of [title, aside, content]
     * @abstract
     * @param User $user
     */
    public function render(GizaUser $user) {

        $calFactory = new Calendar_Api_Factory();
        $calendarHandler = $calFactory->produce();

        $now = time();
        $cList = array(
            'submissions' => $calendarHandler->getSubmissionEvents($now)->getStack(),
            'events' => $calendarHandler->getDayEvents($now)->getStack()
        );

        // check that there aren't events with Alaress_Date_Formats as their date fields (which is a 50:50)
        foreach($cList['events'] as &$e) {
            if($e->getEndTime() instanceof Alaress_Date_Format) {
                $e->setEndTime($e->getEndTime()->getShort());
            }
            if($e->getStartTime() instanceof Alaress_Date_Format) {
                $e->setStartTime($e->getStartTime()->getShort());
            }
            if($e->getDate() instanceof Alaress_Date_Format) {
                $e->setDate($e->getDate()->getShort());
            }
        }

        return array(
            'title'   => "Today's Events",
            'aside'   => '',
            'class'   => 'school-events',
            'icon'    => 'cal',
            'content' => $cList,
        );
    }

}
