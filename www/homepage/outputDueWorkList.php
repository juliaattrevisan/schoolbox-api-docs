<?php
		//due output
		$this->setBlock($componentHandle, "duework", "duework_block");

		if( isStudent($_SESSION['gid']) )
		{
			$query = "
				SELECT	file.id, file.description, file.realname, file.comment,
					DATE_FORMAT(submission_box.date, '%D %b') AS date,
					UNIX_TIMESTAMP(submission_box.open_date) AS open, 
					UNIX_TIMESTAMP(submission_box.late_date) AS late,
					submission_box.id AS box_id, student_submission.owner, inline,
					file_type.name as type
				FROM file
				LEFT JOIN allowedFileTypes ON file.mime_type = mime
				LEFT JOIN submission_box ON	file.id = submission_box.fid
					AND submission_box.deleted = '0'
				LEFT JOIN student_submission ON	student_submission.box_id = submission_box.id 
					AND student_submission.owner = '".$_SESSION['id']."' 
				LEFT JOIN file_type ON submission_box.type = file_type.id
				WHERE file.category = '".$categoryID."' AND file.deleted = '0' 
				ORDER BY submission_box.date DESC
			";
		
		}
		else
		{
			$query = "
			SELECT file.id, file.description, file.comment, 
				DATE_FORMAT(submission_box.date, '%D %b') AS date, 
				submission_box.id AS box_id, submission_box.owner, file.realname, inline,
				file_type.name as type
			FROM file  
				LEFT JOIN allowedFileTypes ON mime_type = mime
				LEFT JOIN submission_box ON file.id = submission_box.fid AND submission_box.deleted = '0'
				LEFT JOIN file_type ON submission_box.type = file_type.id
			WHERE file.category = '".$categoryID."' AND file.deleted = '0'
			ORDER BY submission_box.date DESC";
		}

		//if no size make unlimited query else make it limited by size
		if($config['size'] != 0 && $config['size'] != '') 
			$query .= " LIMIT 0, {$config['size']}"; 

		$this->db->query($query);

		if($this->db->countRows() < 1 ){$this->setVar("duework_block", "");}
		else
		{
			while($queryArray = $this->db->getNextArray() )
			{



						$url = "sendframe.php?id=".$queryArray['id'];
				
						if($queryArray['date'])
							$date = $queryArray['date'];
						else
							$date = "No date";
				
						//if staff or student is enrolled and there is work due provide a link
						if($enroled)
						{
							if(isStudent($_SESSION['gid']) && $queryArray['owner']=='' && $queryArray['late'] > date('U') && $queryArray['open'] < date('U') ) //student
								$dueDate = "<a href=\"/submission/submitWork.php?box_id=".$queryArray['box_id']."\">".$date."</a>";
							else if(isStaff($_SESSION['gid']) && $queryArray['owner'] == $_SESSION['id']) //if staff owns box provide link
								$dueDate = "<a href=\"/submission/viewSubmission.php?box_id=".$queryArray['box_id']."\">".$date."</a>";
							else
								$dueDate = $date;
						}
						else
							$dueDate = $date;
							
						$this->setVar("DUE_DATE", $dueDate);
					
						//set name
						if($queryArray['realname'] != "")
						{
							if($queryArray['inline'] == 'true')
								$url = "<a href=\"sendframe.php?id=".$queryArray['id']."\" target=\"_blank\">".scrubTEXT($queryArray['description'])."</a>";
							else
								$url = "<a href=\"send.php?id=".$queryArray['id']."\">".scrubTEXT($queryArray['description'])."</a>";
				
							$this->setVar("DUE_NAME", $url);
						}
						else
							$this->setVar("DUE_NAME", scrubTEXT($queryArray['description']));
						
						$this->setVar("DUE_COMMENT", nl2br(scrubTEXT($queryArray['comment'])));
						$this->setVar("DUE_TYPE", scrubTEXT($queryArray['type']));
				
						$this->parse("duework_block", "duework", true);




			}
		}

		//setup block for submission button
		$this->setBlock($componentHandle, "submit_dw", "submit_dw_block");
		if(CATEGORY_perms < 2)		
			$this->setVar("submit_dw_block", "");
		else
			$this->parse("submit_dw_block", "submit_dw");	
			
?>