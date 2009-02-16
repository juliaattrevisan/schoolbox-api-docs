<?php
		//assignment
		$this->setBlock($componentHandle, "assign", "assign_block");
		
		require_once '../library/submission.php';
		$submissionHandler = new Submission;
		$assignmentList = $submissionHandler->getAssignments($categoryID, $config);
		
		if (is_array($assignmentList)) {
			foreach ($assignmentList as $assignment) {


				if($assignment['date'])
					$date = outputDate("jS M", $assignment['date']);
				else
					$date = "No date";
		
				//if staff or student is enrolled and there is work due provide a link
				if($enroled)
				{
					if(isStudent($_SESSION['gid']) && !is_numeric($assignment['studentWork']) && $assignment['late'] > date('U') && $assignment['open'] < date('U')) //student
						$dueDate = '<a href="/submission/submitWork.php?box_id='.$assignment['id'].'">'.$date.'</a>';
					else if(isStaff($_SESSION['gid']) && $assignment['owner'] == $_SESSION['id']) //if staff owns box provide link
						$dueDate = '<a href="/submission/viewSubmission.php?box_id='.$assignment['id'].'">'.$date.'</a>';
					else
						$dueDate = $date;
				}
				else
					$dueDate = $date;
					
					$this->setVar("ASS_DATE", $dueDate);
		
				//set name
				if($assignment['realname'])
				{
					if($assignment['inline'] == 'true')
						$url = "<a href=\"/sendframe.php?id=".$assignment['fileID']."\" target=\"_blank\">".scrubTEXT($assignment['description'])."</a>";
					else
						$url = "<a href=\"/send.php?id=".$assignment['fileID']."\">".scrubTEXT($assignment['description'])."</a>";
		
					$this->setVar("ASS_NAME", $url);
				}
				else
					$this->setVar("ASS_NAME", scrubTEXT($assignment['description']));
				
				$this->setVar("ASS_COMMENT", nl2br(scrubTEXT($assignment['comment'])));
		
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