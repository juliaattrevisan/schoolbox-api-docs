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

        $cList = array();

        $cList['submissions'] = $calendarHandler->getSubmissionEvents($now)->getStack();
/*
        if($cList['submissions']) {
            foreach($cList['submissions'] as &$item) {
                $adf = new Alaress_Date_Format($item['date']->getTimestamp(), 'U');
                $item['dueTime'] = $adf->getTimeOnly();
            }
        }
*/
        $cList['events'] = $calendarHandler->getDayEvents($now)->getStack();

/*
        if($cList['events']) {
            foreach($cList['events'] as &$e) {
                if($e['startTime'] == '12:00AM') {
                    unset($e['startTime']);
                }
            }
        }
*/
        return array(
            'title'   => "Today's Events",
            'aside'   => '',
            'class'   => 'school-events',
            'icon'    => 'cal',
            'content' => $cList,
        );
    }

}
