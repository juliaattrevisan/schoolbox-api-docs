<?php
/**
 * ${NAME}
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

$calendarHandler = new Calendar();
$page = new SitePage();

$dateObj = new Alaress_Date_Format($_POST['date'], 'd/m/y');
$date = $dateObj->getUnixTimestamp();

$untilObj = new Alaress_Date_Format($_POST['repeat_until'], 'd/m/y');
$until = $untilObj->getUnixTimestamp();

$event = array(
    'owner'         => $_SESSION['id'],
    'access'        => $_POST['level'],
    'sid'           => $_POST['group'],
    'campusList'    => !empty($_POST['campus']) ? $_POST['campus'] : array($_SESSION['campus'][0]),
    'groupList'     => $_POST['groupCheck'],
    'type'          => $_POST['type'],
    'note'          => $_POST['note'],
    'startTime'     => (($_POST['period'] == 'pm' && $_POST['hour'] != 12 ? 12 : 0) + $_POST['hour']) . ":" . $_POST['minute'] . ":00",
    'endTime'       => (($_POST['toperiod'] == 'pm' && $_POST['tohour'] != 12 ? 12 : 0) + $_POST['tohour']) . ":" . $_POST['tominute'] . ":00",
    'location'      => $_POST['location'],
    'attendance'    => $_POST['attendance'],
    'detail'        => $_POST['detail'],
    'publish_date'  => 'NULL',
);

echo "Creating";