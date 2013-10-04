<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Mobile\Facade\Component;

use Alaress_Date_Format;
use Calendar_Api_Factory;
use Calendar_Event_Type_Abstract;
use Schoolbox\User;

class SchoolEvents extends Generic {

    /**
     * This should return an array of [title, aside, content]
     *
     * @param User $user
     * @return array
     */
    public function render(User $user) {
		$user_mapper = new \Schoolbox\UserMapper();
		$user = $user_mapper->findById($_SESSION['id']);

        $calFactory = new Calendar_Api_Factory($user);
        $calendarHandler = $calFactory->produce();

        $now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');
        $start = new Alaress_Date_Format($now->getDatabaseDate() . ' 00:00:00', 'y-m-d h:i:s');
        $end = new Alaress_Date_Format($now->getDatabaseDate() . ' 23:59:59', 'y-m-d h:i:s');

        $cList = array(
            'events' => $calendarHandler->getEvents($start->getUnixTimestamp(), $end->getUnixTimestamp())->getStack()
        );

        // check that there aren't events with Alaress_Date_Formats as their date fields (which is a 50:50)
        foreach($cList['events'] as &$e) {
            /** @var Calendar_Event_Type_Abstract $e */
            if($e->getEndTime() instanceof Alaress_Date_Format) {
                $e->setEndTime($e->getEndTime()->getTimeOnly());
            }
            if($e->getStartTime() instanceof Alaress_Date_Format) {
                $e->setStartTime($e->getStartTime()->getTimeOnly());
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
