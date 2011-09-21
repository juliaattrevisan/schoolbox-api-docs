<?php
/* When components are added, they go through POST
 */
if (isset($_POST['uid'])) {
	$uid = Scrub::text($_POST['uid']);
}
else {
	$uid = $this->arg[0];
}

$objectiveComponent = new Eportfolio_Objective_Goal($this, $component['eportfolioComponentLocationID']);
$objectiveComponent->setUserId($uid);
$objectiveComponent->render();

