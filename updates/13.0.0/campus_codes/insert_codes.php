<?php
/**
 * Copies old config defines from config file and puts them into database
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */
require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

$timetableCodes = unserialize(SYN_CAMPUS_Codes);
$dayCode = unserialize(TIMETABLE_day_count);

$campusMapper = new \Schoolbox\CampusMapper();


foreach($timetableCodes as $key => $code) {

	if($campus = $campusMapper->findById($key)) {
		$campus->setExtTimetableCode($code);
		$campusMapper->save($campus);
	}
}

foreach($dayCode as $key => $day) {
	if($campus = $campusMapper->findById($key)) {
		$campus->setTimetableDay($day);
		$campusMapper->save($campus);
	}
}

