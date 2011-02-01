<?php
//due output
$this->setBlock($componentHandle, "duework", "duework_block");


require_once SITE_root.'/library/submission.php';
$submissionHandler = new Submission;
$submissionList = $submissionHandler->getAllForCategory($categoryID, $config);

if (is_array($submissionList)) {

	foreach($submissionList as $submission) {

		$nameURL = $submission['realname'] != '' ? "/send" . ($submission['inline'] == 'true' ? 'frame' : '') . ".php?id=" . $submission['fileID'] : false;

		$dateHTML = outputDate("jS M", $submission['date']);
		$dateURL = false;

		if($enroled) {
                    if($groupHandler->isStaff() && $submission['owner'] == $_SESSION['id']) {
			$dateURL = "/submission/viewSubmission/" . $submission['id'];
                        $dateHTML .= ' | ' . '<a href="' . $dateURL . '">View Work</a>';
                    }
                    else if(!is_numeric($submission['studentWork']) && $submission['late'] > time() && $submission['open'] < time()) {
			$dateURL = "/submission/submitWork/" . $submission['id'];
                        $dateHTML .= ' | ' . '<a href="' . $dateURL . '">Submit Work</a>';
                    }
		}

		$nameHTML = ($nameURL ? '<a href="'.$nameURL.'">' : "") . scrubText($submission['description']) . ($nameURL ? '</a>' : "");

        /* Made this a bit easier to understand and modify :P
         * The date, as a link, is not an obvious way to submit work
         *
        $dateHTML = ($dateURL ? '<a href="'.$dateURL.'">' : "") . outputDate("jS M", $submission['date']) . ($dateURL ? '</a>' : "");
         */
        

		$output = array(
			'DUE_NAME'	=> $nameHTML,
			'DUE_TYPE'	=> scrubTEXT($submission['typeName']),
			'DUE_DATE'	=> $dateHTML,
			'DUE_COMMENT' => nl2br(scrubTEXT($submission['comment'])),
			'ON_OFF' => ++$j%2? '' : 'dark',
			'SUBMIT_URL' => $dateURL,
		);

		$this->setVar($output);
		$this->parse("duework_block", "duework", true);
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