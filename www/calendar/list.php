<?php
/**
 * Render the javascript-based Fullcalendar implementation
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
$user_mapper = new \Schoolbox\UserMapper();

/* If both args were provided, assume date and userId respectively
 * Otherwise just assume currently logged in user
 */
if (count($this->arg) == 2) {
    $selectedDate = $this->arg[0];
    try {
        Calendar_Layout_List::permissionCheck($this->arg[1], $_SESSION['user'], new Group());
        $user = $user_mapper->findById($this->arg[1]);
    }
    catch (Exception $e) {
        error($e->getCode());
    }
}
else {
    $selectedDate = $this->arg[0];
    $user = $_SESSION['user'];
}

$factory = new Calendar_Api_Factory($user);
$api = $factory->produce();

$calendar = new Calendar_Event_Collection_Lister($api, $_SERVER['REQUEST_TIME'],  strtotime('+1 year', $_SERVER['REQUEST_TIME']), $this);
$calendar->renderAll();

$this->setVar(array(
    'USER_FULLNAME' => $user->getFullname()
));