<?php
$objectiveComponent = new Eportfolio_Objective_EducationHistory($this, $component['eportfolioComponentLocationID']);

/* When components are added, they go through POST
 */
if (isset($_POST['uid'])) {
	$uid = Scrub::text($_POST['uid']);
}
else {
	$uid = $this->arg[0];
}

$objectiveComponent->setUserId($uid);
$objectiveComponent->render();


