<?php
//due output
$this->setBlock($componentHandle, "duework", "duework_block");


require_once SITE_root.'/library/submission.php';
$submissionHandler = new Submission;
$submissionList = $submissionHandler->getAllForCategory($categoryID, $config);

if (is_array($submissionList)) {

	foreach($submissionList as $submission) {

		$nameURL = $submission['realname'] != '' ? "/send" . ($submission['inline'] == 'true'&& FILE_use_frame ? 'frame' : '') . ".php?id=" . $submission['fileID'] : false;

		$date = new Alaress_Date_Format($submission['date'], 'U');

		$dateHTML = $date->getDateOnly();
		$dateURL = false;

		$nameHTML = ($nameURL ? '<a href="'.$nameURL.'">' : "") . Scrub::text($submission['name']) . ($nameURL ? '</a>' : "");

        /* Actions Block */
        $this->setBlock('duework', 'duework_actions', 'duework_actions_block');

        if($groupHandler->isStudent()) {
            $this->setVar('DUE_ACTION', '/submission/submissionHistory/' . $submission['id']);

            if (!empty($submission['submitted'])) {
                if (!empty($submission['returned'])) {
                    $this->setVar('DUE_ACTION_NAME', 'View Mark');
                } else {
                    $this->setVar('DUE_ACTION_NAME', 'Awaiting Mark');
                }
            } else {
                if (time() > $submission['open'] && time() < $submission['late']) {
                    $this->setVar('DUE_ACTION_NAME', 'Submit Work');
                } else {
                    $this->setVar('DUE_ACTION_NAME', 'View Details');
                }
            }

            $this->parse('duework_actions_block', 'duework_actions');
        } elseif ($groupHandler->isStaff() && $submission['owner'] == $_SESSION['id']) {
            $this->setVar('DUE_ACTION', '/submission/viewSubmission/' . $submission['id']);
            $this->setVar('DUE_ACTION_NAME', 'View Work');

            $this->parse('duework_actions_block', 'duework_actions');
        } else {
            $this->setVar('duework_actions_block', '');
        }

		$output = array(
            'DUE_ID'    => $submission['id'],
			'DUE_NAME'	=> $nameHTML,
			'DUE_TYPE'	=> Scrub::text($submission['typeName']),
			'DUE_DATE'	=> $dateHTML,
			'DUE_COMMENT' => nl2br(Scrub::text($submission['comment'])),
			'ON_OFF' => ++$j%2? '' : 'dark',
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