<?php
/**
 * Get a list of parents' email addresses, for a given classCode
 * and set of externalIds
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once('../../../../library/Bootstrap.php');

/* avviare la sessione con il solito sitepage
 */
$page = new SitePage();
$externalClass = new External_Class();
$externalHandler = new External_User();

$ids = array();
$emailString = array();

/* Find all the students whose parents we'll need to contact
 */
$allStudents = $externalClass->getStudents($_POST['classCode']);

if ($allStudents && count($allStudents) && is_array($_POST['allIds']) && count($_POST['allIds'])) {
	foreach ($allStudents as $currentStudent) {
		if (in_array($currentStudent['synergy_id'], $_POST['allIds'])) {
			$ids[] = $currentStudent['synergy_id'];
		}
	}
}

/* Now get the parents of the students
 */
$allParents = $externalHandler->getParentEmails($ids);

if ($allStudents && count($allParents)) {
	foreach ($allParents as $currentParent) {
		$emailString[] = $currentParent;
	}
}

/* Uniqueify emails
 */
$emailString = array_values(array_filter(array_unique($emailString)));

echo json_encode($emailString);
