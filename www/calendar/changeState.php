<?php
/**
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Calendar\Util\ViewNameMapping;
use Schoolbox\UserMapper;

/* Must be one of these options (there might be consts somewhere else)
*/
if (!\array_key_exists($_POST['calendarView'], ViewNameMapping::MAP_STORED_TO_FC)) {
    return;
}
$storedView = ViewNameMapping::MAP_STORED_TO_FC[$_POST['calendarView']];

$userMapper = UserMapper::getInstance();
$user = $userMapper->findById($_SESSION['user']->getId());
$_SESSION['user']->setCalendarView($storedView);
$user->setCalendarView($storedView);
UserMapper::getInstance()->save($user);

exit;
