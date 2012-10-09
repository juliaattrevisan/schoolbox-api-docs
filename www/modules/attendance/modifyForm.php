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
$username = 'sa';
$password = 'school';
$installGUID = '?';
//
//$responseData = $api->loginUser($username, $password, $installGUID);
//
/* Create and initialise temporary work table
 */
//$today = new Alaress_Date_Format(
$fileType = 'A';
$fileYear = '2010';
$fileSemester = '1';
$campus = 'S';
$classCode = $_POST['classCode'];
$attendanceDate = '2010-10-08';
$staffID = '8';
$timetableGroup = 'SEN';
