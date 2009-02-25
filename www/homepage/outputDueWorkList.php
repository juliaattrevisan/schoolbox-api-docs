<?php
//due output
$this->setBlock($componentHandle, "duework", "duework_block");


require_once '../library/submission.php';
$submissionHandler = new Submission;
$submissionList = $submissionHandler->getAllForCategory($categoryID, $config);

if (is_array($submissionList)) {
	foreach($submissionList as $submission) {

		$nameURL = $submission['realname'] != '' ? "/send" . ($submission['inline'] == 'true' ? 'frame' : '') . ".php?id=" . $submission['fileID'] : false;

		$date = outputDate("jS M", $submission['date']);
		$dateURL = false;

		if($enroled) {
			if(isStudent($_SESSION['gid']) && !is_numeric($submission['studentWork']) && $submission['late'] > time() && $submission['open'] < time())
				$dateURL = "/submission/submitWork.php?box_id=" . $submission['id'];
			else if(isStaff($_SESSION['gid']) && $submission['owner'] == $_SESSION['id'])
				$dateURL = "/submission/viewSubmission.php?box_id=" . $submission['id'];
		}
		
		$nameHTML = ($nameURL ? '<a href="'.$nameURL.'">' : "") . scrubText($submission['description']) . ($nameURL ? '</a>' : "");
		$dateHTML = ($dateURL ? '<a href="'.$dateURL.'">' : "") . outputDate("jS M", $submission['date']) . ($dateURL ? '</a>' : "");

		$output = array('DUE_NAME'	=> $nameHTML,
						'DUE_TYPE'	=> scrubTEXT($submission['typeName']),
						'DUE_DATE'	=> $dateHTML,
						'DUE_COMMENT' => nl2br(scrubTEXT($submission['comment'])),										
						);
						
		$this->setVar($output);
		$this->parse("duework_block", "duework", true);
		$this->setVar('ON_OFF', ++$i%2? '' : 'dark');
	}
} else
	$this->setVar('duework_block', '');

//setup block for submission button
$this->setBlock($componentHandle, "submit_dw", "submit_dw_block");
if($permission < WRITE_ACCESS)		
	$this->setVar("submit_dw_block", "");
else
	$this->parse("submit_dw_block", "submit_dw");	
			
?>