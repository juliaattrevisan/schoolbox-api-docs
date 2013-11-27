<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once('../../../../include/Bootstrap.php');

/* avviare la sessione con il solito sitepage
 */
$page = new SitePage();
$externalClass = new External_Class();
$userHandler = new User();

$ids = array();
$emailString = array();

/* Find all the students whose parents we'll need to contact
 */
$allStudents = $externalClass->getStudents($_POST['classCode']);

if ($allStudents && count($allStudents)) {
	foreach ($allStudents as $currentStudent) {
		if (in_array($currentStudent['synergy_id'], $_POST['allIds'])) {
			$user = $userHandler->getBySynergy($currentStudent['synergy_id']);

			$emailObj = new Email();
			$emailString[] = $emailObj->formatEmailFromArray($user);
		}
	}
}

echo json_encode($emailString);
