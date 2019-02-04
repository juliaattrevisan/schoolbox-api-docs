<?php
/**
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
use Schoolbox\UserMapper;

/* Must be one of these options (there might be consts somewhere else)
*/
if (!\in_array($_POST['calendarView'], [ 'month', 'agendaWeek', 'agendaDay' ], true)) {
    return;
}

$userMapper = UserMapper::getInstance();
$user = $userMapper->findById($_SESSION['user']->getId());
$_SESSION['user']->setCalendarView($_POST['calendarView']);
$user->setCalendarView($_POST['calendarView']);
UserMapper::getInstance()->save($user);

exit;