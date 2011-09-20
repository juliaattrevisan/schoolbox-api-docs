<?php
require_once(dirname(__FILE__) . '/../../../../include/Bootstrap.php');
session_start();

/* Create/update
 */
if (isset($_POST['body'])) {
	$skill = new Eportfolio_Objective_Interest(null, Scrub::text($_POST['componentLocationID']));
	$skill->setUserId(Scrub::text($_POST['userID']));
	$skill->createUpdate($_POST);
}
/* Delete
 */
else if (is_numeric($_POST['delete'])) {
	$skill = new Eportfolio_Objective_Interest(null, Scrub::text($_POST['delete']));
	$skill->setUserId(Scrub::text($_POST['userID']));
	$skill->delete();
}
else {
	error(2);
}

