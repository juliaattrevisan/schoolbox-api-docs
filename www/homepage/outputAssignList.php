<?php

require_once '../library/submission.php';

$submissionHandler = new Submission;
$assignmentList = $submissionHandler->getAssignments($categoryID, $config);

$this->setBlock($componentHandle, "assign", "assign_block");

if (is_array($assignmentList)) {
	foreach ($assignmentList as $assignment) {

		if($assignment['date'])
			$date = outputDate("jS M", $assignment['date']);
		else
			$date = "No date";

		//if staff or student is enrolled and there is work due provide a link
		if($enroled)
		{
			if($groupHandler->isStudent() && !is_numeric($assignment['studentWork']) && $assignment['late'] > date('U') && $assignment['open'] < date('U')) //student
				$dueDate = '<a href="/submission/submitWork/'.$assignment['id'].'">'.$date.'</a>';
			else if($groupHandler->isStaff() && $assignment['owner'] == $_SESSION['id']) //if staff owns box provide link
				$dueDate = '<a href="/submission/viewSubmission/'.$assignment['id'].'">'.$date.'</a>';
			else
				$dueDate = $date;
		}
		else
			$dueDate = $date;
			
			$this->setVar("ASSIGN_DATE", $dueDate);

		//set name
		if($assignment['realname'])
		{
			if($assignment['inline'] == 'true')
				$url = "<a href=\"/sendframe.php?id=".$assignment['fileID']."\" onclick=\"window.open(this.href,'_blank',''); return false;\">".scrubTEXT($assignment['description'])."</a>";
			else
				$url = "<a href=\"/send.php?id=".$assignment['fileID']."\">".scrubTEXT($assignment['description'])."</a>";

			$this->setVar("ASSIGN_NAME", $url);
		}
		else
			$this->setVar("ASSIGN_NAME", scrubTEXT($assignment['description']));
		
		$this->setVar("ASSIGN_COMMENT", nl2br(scrubTEXT($assignment['comment'])));
		
		$this->setVar('ON_OFF', ++$j%2? '' : 'dark');
		$this->parse("assign_block", "assign", true);
		



	}
} else
	$this->setVar("assign_block", "");

//setup block for submission button
$this->setBlock($componentHandle, "submit_ass", "submit_ass_block");
if($permission < WRITE_ACCESS)		
	$this->setVar("submit_ass_block", "");
else
	$this->parse("submit_ass_block", "submit_ass");	

?>