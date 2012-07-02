<?php
/**
 * ${NAME}
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */

$api = new External_Synergetic_Gateway(Cfg::get('external_web_services_url'), Cfg::get('external_vendor_token_guid'), Cfg::get('external_vendor_password'));

/* Log in user
 */
$username = 'cglenn';
$password = 'password';
$installGUID = '?';

$responseData = $api->loginUser($username, $password, $installGUID);

/* Create and initialise temporary work table
 */
$fileType = 'A';
$fileYear = '2012';
$fileSemester = '2';
$campus = 'J';
$classCode = '00EN1';
$attendanceDate = '2012-06-23';
$staffID = '484';
$timetableGroup = 'JUN';

$responseData = $api->insertWkAttendances($fileType, $fileYear, $fileSemester, $campus, $classCode, $attendanceDate, $staffID, $timetableGroup);
$wkAttendanceMasterSeq = $responseData->wkAttendanceMasterSeq;

/* Select people
 */
$responseData = $api->selectWkAttendances($wkAttendanceMasterSeq);

if (count($responseData)) {
	foreach($responseData as $current) {
		// render $current w/checkboxes for those who were absent
	}
}

/* Update attendances in temporary table
 */
$absentIDs = $_POST['absentIDs'];

$api->updateWkAttendances($wkAttendanceMasterSeq, $absentIDs);

/* Transfer temporary table contents to somewhere else
 */
$api->transferWkAttendances($wkAttendanceMasterSeq);


