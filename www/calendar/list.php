<?php
/**
 * Render the javascript-based Fullcalendar implementation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
$userMapper = new \Schoolbox\UserMapper();

/* If three args were provided, assume userId and two dates respectively
 * Otherwise just assume currently logged in user
 */

/* Viewing someone else's calendar:
 * Url format: /calendar/list/{userId}/{startDate}/{finishDate}
 */
if (count($this->arg) >= 2) {
    /* Check that the user exists
     */
    try {
        $user = $userMapper->findById($this->arg[0]);
        Calendar_Layout_Full_Javascript::permissionCheck($this->arg[0], $_SESSION['user'], new Group());
    } catch (Exception $e) {
        error($e->getCode());
    }

    /* Take them on a date
     */
    $start = new \Alaress_Date_Format($this->arg[1], 'd-m-Y');

    if (isset($this->arg[2])) {
        $finish = new \Alaress_Date_Format($this->arg[2], 'd-m-Y');
    } else {
        $finish = new \Alaress_Date_Format(strtotime('+1 month', $start->getUnixTimestamp()), 'U');
    }
}
/* Viewing your own calendar:
 * Url format: /calendar/list/{startDate}
 */
else {
    $user = $_SESSION['user'];

    /* Take them on a date
     */
    $start  = new \Alaress_Date_Format($this->arg[0], 'd-m-Y');
    $finish = new \Alaress_Date_Format(strtotime('+1 month', $start->getUnixTimestamp()), 'U');
}

$factory = new Calendar_Api_Factory($user);
$api     = $factory->produce();

$this->setVar(
    array(
        'USER_ID'                   => $user->getId(),
        'USER_TOKEN'                => $user->getKey(),
        'CALENDAR_PARAMS'           => "{$start->getURLDate()}/{$user->getId()}",
        'SELECTED_START'            => $start->getLocalShort(),
        'SELECTED_FINISH'           => $finish->getLocalShort(),
        'SELECTED_FORMATTED_START'  => $start->getURLDate(),
        'SELECTED_FORMATTED_FINISH' => $finish->getURLDate(),
    )
);

// Extend finish by one day for search
$calendar = new Collection_Lister(
    $api,
    $start->getUnixTimestamp(),
    $finish->addDays(1)->getUnixTimestamp(),
    $this,
    $user
);

$calendar->renderAll();
