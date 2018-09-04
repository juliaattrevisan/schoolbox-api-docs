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

$_SESSION['user']->setCalendarView($_POST['calendarView']);
UserMapper::getInstance()->save($_SESSION['user']);

exit;