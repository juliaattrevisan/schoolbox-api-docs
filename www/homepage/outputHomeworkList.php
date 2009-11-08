<?php
//homework output
$this->setBlock($componentHandle, "homework", "homework_block");

require_once '../library/submission.php';
$submissionHandler = new Submission;
$homeworkList = $submissionHandler->getHomework($categoryID, $config);

if (is_array($homeworkList)) {
	foreach ($homeworkList as $homework) {

		if($homework['date'])
			$date = outputDate("jS M", $homework['date']);
		else
			$date = "No date";

		//if staff or student is enrolled and there is work due provide a link
		if($enroled)
		{
			if($groupHandler->isStudent() &&!is_numeric($homework['studentWork']) && $homework['late'] > date('U') && $homework['open'] < date('U') ) //student
				$dueDate = "<a href=\"/submission/submitWork.php?box_id=".$homework['id']."\">".$date."</a>";
			else if($groupHandler->isStaff() && $homework['owner'] == $_SESSION['id']) //if staff owns box provide link
				$dueDate = "<a href=\"/submission/viewSubmission.php?box_id=".$homework['id']."\">".$date."</a>";
			else
				$dueDate = $date;
		}
		else
			$dueDate = $date;
			
		$this->setVar("HW_DATE", $dueDate);
		$this->setVar('ON_OFF', ++$j%2? '' : 'dark');
	
		//set name
		if($homework['realname'])
		{
			if($homework['inline'] == 'true' && FILE_use_frame)
				$url = "<a href=\"/sendframe.php?id=".$homework['fileID']."\" target=\"_blank\">".scrubTEXT($homework['description'])."</a>";
			else
				$url = "<a href=\"/send.php?id=".$homework['fileID']."\">".scrubTEXT($homework['description'])."</a>";

			$this->setVar("HW_NAME", $url);
		}
		else
			$this->setVar("HW_NAME", scrubTEXT($homework['description']));
		
		$this->setVar("HW_COMMENT", nl2br(scrubTEXT($homework['comment'])));
		
		$this->parse("homework_block", "homework", true);


	}
} else
	$this->setVar("homework_block", "");

//setup block for submission button
$this->setBlock($componentHandle, "submit_hw", "submit_hw_block");
if ($permission < WRITE_ACCESS)		
	$this->setVar("submit_hw_block", "");
else
	$this->parse("submit_hw_block", "submit_hw");	
		
?>