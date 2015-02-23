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

/* Calendar controls execute post
 */
if (!empty($_POST)) {
    try {
        $user = $userMapper->findById($_POST['userKey']);
        Calendar_Layout_Full_Javascript::permissionCheck($_POST['userKey'], $_SESSION['user'], new Group());
    } catch (Exception $e) {
        error($e->getCode());
    }

    $start  = new \Alaress_Date_Format($_POST['start_date'], 'd/m/y');
    $finish = new \Alaress_Date_Format($_POST['finish_date'], 'd/m/y');
}
/* If both args were provided, assume date and userId respectively
 * Otherwise just assume currently logged in user
 */
else {
    if (count($this->arg) == 2) {
        $selectedDate = $this->arg[0];

        try {
            $user = $userMapper->findById($this->arg[1]);
            Calendar_Layout_Full_Javascript::permissionCheck($this->arg[1], $_SESSION['user'], new Group());

        } catch (Exception $e) {
            error($e->getCode());
        }
    } else {
        $selectedDate = $this->arg[0];
        $user         = $_SESSION['user'];
    }

    /* Take them on a date
     */
    $date = new \Alaress_Date_Format($selectedDate, 'd-m-y');

    $start  = new \Alaress_Date_Format($date->getUnixTimestamp(), 'U');
    $finish = new \Alaress_Date_Format(strtotime('+1 month', $date->getUnixTimestamp()), 'U');
}

$factory = new Calendar_Api_Factory($user);
$api     = $factory->produce();

$this->setVar(
    array(
        'USER_ID'         => $user->getId(),
        'USER_TOKEN'      => $user->getKey(),
        'CALENDAR_PARAMS' => implode('/', $this->arg),
        'SELECTED_START'  => $start->getUsingFormat('d/m/Y'),
        'SELECTED_FINISH' => $finish->getUsingFormat('d/m/Y'),
    )
);

// Extend finish by one day for search
$calendar = new Calendar_Event_Collection_Lister(
    $api,
    $start->getUnixTimestamp(),
    $finish->addDays(1)->getUnixTimestamp(),
    $this,
    $user
);

$calendar->renderAll();
