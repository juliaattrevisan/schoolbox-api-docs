<?php
if (is_numeric($this->arg[0])) {
    $this->setFile(TEMPLATE_content, 'submission/viewSubmission.html');
    $this->setTitle('Submission Box');
	$this->setTab('submission');
    $this->setBlock(TEMPLATE_content, 'submission_block', 'submission_listing');
    $this->setBlock('submission_block', 'download_action', 'download_action_block');
    $this->setBlock('submission_block', 'delete_action', 'delete_action_block');
    $this->setBlock('submission_block', 'mark_action', 'mark_action_block');

    $submissionHandler = new Submission();
    $studentSubHandler = new SubmissionStudent();
	$returnHandler = new SubmissionReturn();

    $subBox = $submissionHandler->getByID($this->arg[0]);

    if($subBox['owner'] != $_SESSION['id'] ) {
        error(17);
    }

    $submissionHandler->logPageView(3, $this->arg[0]);

    $this->setVar('ASSDESC', Scrub::text($subBox['categoryName'])." - ".Scrub::text($subBox['name']));
	$this->setVar('CATEGORY_ID', $subBox['cid']);
    $this->setVar('BOX_ID', $subBox['id']);
    $dueDate = new Alaress_Date_Format($subBox['date'], 'y-m-d');
    $this->setVar('DUE_DATE', $dueDate->getLong());

    /* Get full student list of class to show those who have not submitted
     * If synergy code get synergy students
     */
    if ($subBox['code'] && Cfg::get('external_enable')) {
        $synHandler = new External_Class();
        $studentList = $synHandler->getStudents($subBox['code']);
    }
	/* Retrieve student list from group membership
	 */
    else {
        $groupHandler = new Group();
        $studentList = $groupHandler->getMemberList($subBox['category']);
    }

    $userHandler = new User();

    if (is_array($studentList)) {
        foreach($studentList as $student) {
            if ($student['uid']) {
				$user = $userHandler->getByID($student['uid']);
				$studentSub = $studentSubHandler->getByUserID($this->arg[0], $student['uid']);
			}
            else if ($student['synergy_id']) {
				$user = $userHandler->getBySynergy($student['synergy_id']);
                $studentSub = $studentSubHandler->getBySynergyID($this->arg[0], $student['synergy_id']);
			}
			else {
				continue;
			}

			if (!is_array($user)) {
				continue;
			}

            $return = $returnHandler->getByUser($user['uid'], $this->arg[0]);

            $i = 0;
            if (is_array($studentSub)) {
                // if multi-submit has been disabled, only display the most recent submission
                if(!$subBox['multi_submission']) {
                   $studentSub = array($studentSub[0]);
                }

                $rtnDate = new Alaress_Date_Format($return[0]['returned'], 'y-m-d j:i:s');

                foreach($studentSub as $submission) {

                    $subDate = new Alaress_Date_Format($submission['datestamp'], 'y-m-d j:i:s');

                    if($subDate->getUnixTimestamp() > $rtnDate->getUnixTimestamp() && $rtnDate->getUnixTimestamp() > 0) {
                        $exc = '<span>!</span>';
                    }
                    else {
                        $exc = '';
                    }

                    $output = array(
                        'OWNER' => $userHandler->formatName($user, '{lastname}, {firstname}'),
                        'OWNER_ID' => $user['uid'],
                        'OWNER_ESCAPED' => addslashes($userHandler->formatName($student, '{lastname}, {firstname}')),
                        'SUB_ID' => $submission['id'],
                        'SUBMITTED' => $submission['date'],
                        'SUBMITTED_AFTER_MARK' => $exc,

                        // check if submission date if after due date; if so, mark as late (ignore $studentSub['late'])
                        'COLOUR' => $dueDate->getUnixTimestamp() < $subDate->getUnixTimestamp() ? '#ff0000' : '#000000',

                        // only display the mark on the top row per student
                        'SCORE' => $submission['score'],
                    );

                    if($i == 0) {
                        $output['MARK'] = $return[0]['visible_mark'];
                        $output['SUBMISSION_EXPANDABLE'] = '';
                        $output['SUBMISSION_HIDDEN'] = '';
                        $this->setVar($output);
                        $this->parse('mark_action_block', 'mark_action', false);
                    }
                    else {
                        $output['MARK'] = '';
                        $output['SUBMISSION_EXPANDABLE'] = 'expandable';
                        $output['SUBMISSION_HIDDEN'] = 'hide';
                        $output['mark_action_block'] = '';
                        $this->setVar($output);
                    }

                    $i++;

                    $this->parse('download_action_block', 'download_action', false);
                    $this->parse('delete_action_block', 'delete_action', false);

                    $this->parse('submission_listing', 'submission_block', true);
                }
            }
            else {
                $output = array(
					'OWNER' => $userHandler->formatName($user, '{lastname}, {firstname}'),
					'OWNER_ID' => $user['uid'],
					'OWNER_ESCAPED' => addslashes($userHandler->formatName($student, '{lastname}, {firstname}')),
					'SUBMITTED' => '',
					'COLOUR' => '',
					'MARK' => $return[0]['visible_mark'],
                    'SCORE' => '',
                    'SUBMISSION_EXPANDABLE' => '',
                    'SUBMISSION_HIDDEN' => '',
                );

                $this->setVar($output);

                $this->setVar('download_action_block', '');
                $this->setVar('delete_action_block', '');
                $this->parse('mark_action_block', 'mark_action', false);
                $this->parse('submission_listing', 'submission_block', true);
            }

        }
    }
    else {
        $submissionList = $studentSubHandler->getByBoxID($this->arg[0]);

        if (is_array($submissionList)) {
            foreach($submissionList as $line) {

				/* If already marked display mark, else provide link to return mark */
                $return = $returnHandler->getByUser($line['uid'], $this->arg[0]);

                if ( empty($line['score']) ) {
                    $score = '-';
                }
                else {
                    $score = "{$line['score']}%";
                }

                $output = array(
					'OWNER' => $userHandler->formatName($line, '{lastname}, {firstname}'),
					'OWNER_ID' => $line['uid'],
					'SUB_ID' => $line['id'],
					'OWNER_ESCAPED' => addslashes($userHandler->formatName($line, '{lastname}, {firstname}')),
					'SUBMITTED' => $line['date'],
					'COLOUR' => $line['late'] ? '#ff0000' : '#000000',
					'MARK' => $return[0]['mark'],
                    'SCORE' => $score,
            	);

				$this->setVar($output);
				$this->parse('download_action_block', 'download_action');
				$this->parse('mark_action_block', 'mark_action', false);
				$this->parse('submission_listing', 'submission_block', true);
				$this->parse('delete_action_block', 'delete_action');
            }
        }
		else {
			$this->setVar('submission_listing', '');
		}
    }
}
else {
    error(2);
}
