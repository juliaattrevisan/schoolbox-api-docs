<?php
//homework output
$this->setBlock($componentHandle, "homework", "homework_block");

require_once SITE_root.'/library/submission.php';
$submissionHandler = new Submission;
$homeworkList = $submissionHandler->getHomework($categoryID, $config);

if (is_array($homeworkList)) {
	foreach ($homeworkList as $homework) {

            	$nameURL = $homework['realname'] != '' ? "/send" . ($homework['inline'] == 'true' ? 'frame' : '') . ".php?id=" . $homework['fileID'] : false;

		$date = new Alaress_Date_Format($homework['date'], 'U');
		$dateHTML = $date->getShort();
		$dateURL = false;

		if($enroled) {
                    if($groupHandler->isStaff() && $homework['owner'] == $_SESSION['id']) {
			$dateURL = "/submission/viewSubmission/" . $homework['id'];
                        $dateHTML .= ' | ' . '<a href="' . $dateURL . '">View Work</a>';
                    }
                    else if(!is_numeric($homework['studentWork']) && $homework['late'] > time() && $homework['open'] < time()) {
			$dateURL = "/submission/submitWork/" . $homework['id'];
                        $dateHTML .= ' | ' . '<a href="' . $dateURL . '">Submit Work</a>';
                    }
		}

		$nameHTML = ($nameURL ? '<a href="'.$nameURL.'">' : "") . Scrub::text($homework['name']) . ($nameURL ? '</a>' : "");

		$output = array(
			'HW_NAME'	=> $nameHTML,
			'HW_DATE'	=> $dateHTML,
			'HW_COMMENT' => nl2br(Scrub::text($homework['comment'])),
			'ON_OFF' => ++$j%2? '' : 'dark',
		);

		$this->setVar($output);
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