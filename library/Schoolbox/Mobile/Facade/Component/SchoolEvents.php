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
        $cList = array(
            'events' => $calendarHandler->getEvents(
                Alaress_Date_Format::now()->startOfDay()->getUnixTimeStamp(),
                Alaress_Date_Format::now()->addDays(1)->startOfDay()->getUnixTimeStamp()
            )->getStack()
        );

        return array(
            'title'   => "Today's Events",
            'aside'   => '',
            'class'   => 'school-events',
            'icon'    => 'cal',
            'content' => $cList,
        );
    }

}
