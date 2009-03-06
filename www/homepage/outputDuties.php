<?php

$this->setBlock($componentHandle, "duties", "d_block");

require_once '../library/duty.php';
$dutyHandler = new Duty;

$dutyList = $dutyHandler->getAllInCategory($categoryID);
$this->setVar("DATE", 'Duties for '.date('l, jS \of F Y'));

$block = false;
if (is_array($dutyList)) {
	foreach ($dutyList as $duty) {
	
			$this->setVar("DUTY", $duty['name']);
			$this->setVar("ASSIGNEDUSER", $duty['user']);
			$this->parse("d_block", "duties", true);
			$block = true;
			$this->setVar('ON_OFF', ++$j%2? '' : 'dark');
	}
} else {
		$this->setVar("DUTY", "No Duties set for today");
		$this->setVar("ASSIGNEDUSER", '');
		$this->parse("d_block", "duties", true);
}

$this->setBlock($componentHandle, "edit_duties", "edit_duties_block");
if ($permission < WRITE_ACCESS)		
		$this->setVar("edit_duties_block", "");
else
		$this->parse("edit_duties_block", "edit_duties");
					
?>