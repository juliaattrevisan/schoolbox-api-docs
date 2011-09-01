<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionStudent.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionReturn.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';

if(is_numeric($this->arg[0])) {

    $this->setFile(TEMPLATE_content, 'submission/viewSubmission.html');
    $this->setTitle('Submission Box');
	$this->setTab('submission');
    $this->setBlock(TEMPLATE_content, 'submission_block', 'submission_listing');
    $this->setBlock('submission_block', 'download_action', 'download_action_block');
    $this->setBlock('submission_block', 'delete_action', 'delete_action_block');
    $this->setBlock('submission_block', 'mark_action', 'mark_action_block');

    $submissionHandler = new Submission;
    $studentSubHandler = new SubmissionStudent;
	$returnHandler = new SubmissionReturn;

    $subBox = $submissionHandler->getByID($this->arg[0]);

    if($subBox['owner'] != $_SESSION['id'] ) {
        error(17);
    }

    $submissionHandler->logPageView(3, $this->arg[0]);

    $this->setVar('ASSDESC', scrubTEXT($subBox['categoryName'])." - ".scrubTEXT($subBox['name']));
	$this->setVar('CATEGORY_ID', $subBox['cid']);
    $this->setVar('BOX_ID', $subBox['id']);

    /* Get full student list of class to show those who have not submitted */
    if($subBox['code'] && Cfg::get('external_enable')) {

        //if synergy code get synergy students
        $synHandler = new ExternalClass();
        $studentList = $synHandler->getStudents($subBox['code']);
    }
    else {
        //Retrieve student list from group membership
        require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
        $groupHandler = new Group;
        $studentList = $groupHandler->getMemberList($subBox['category']);
    }


    $userHandler = new User;

    if(is_array($studentList)) {
        foreach($studentList as $student) {

            if($student['uid']) {
				$user = $userHandler->getByID($student['uid']);
				$studentSub = $studentSubHandler->getByUserID($this->arg[0], $student['uid']);
			}
            else if($student['synergy_id']) {
				$user = $userHandler->getBySynergy($student['synergy_id']);
                $studentSub = $studentSubHandler->getBySynergyID($this->arg[0], $student['synergy_id']);
			}
            else
                continue;

            if(!is_array($user))
                continue;

            /* If already marked display mark, else provide link to return mark */
            $return = $returnHandler->getByUser($user['uid'], $this->arg[0]);

            if(is_array($studentSub)) {

                $output = array(
                    'OWNER' 	=> $userHandler->formatName($user,'{lastname}, {firstname}'),
                    'OWNER_ID' 	=> $user['uid'],
                    'OWNER_ESCAPED' => addslashes($userHandler->formatName($student,'{lastname}, {firstname}')),
                    'SUB_ID'    => $studentSub['id'],
                    'SUBMITTED' => $studentSub['date'],
                    'COLOUR'	=> $studentSub['late']?'#ff0000':'#000000',
                    'MARK'	    => $return[0]['mark'],
                    'ON_OFF'	=> ++$i%2?'':'dark',
                    'REMARK'    => $remark,
                );

                $this->setVar($output);

                $this->parse('download_action_block', 'download_action', false);
                $this->parse('delete_action_block', 'delete_action', false);
            }
            else {

                $output = array(
                    'OWNER' 	=> $userHandler->formatName($user,'{lastname}, {firstname}'),
                    'OWNER_ID' 	=> $user['uid'],
                    'OWNER_ESCAPED' => addslashes($userHandler->$userHandler->formatName($student,'{lastname}, {firstname}')),
                    'SUBMITTED'	=> '',
                    'COLOUR'	=> '',
                    'MARK'	    => $return[0]['mark'],
                    'ON_OFF'	=> ++$i%2?'':'dark',
                );

                $this->setVar($output);

                $this->setVar('download_action_block', '');
                $this->setVar('delete_action_block', '');
            }

            $this->parse('mark_action_block', 'mark_action', false);
            $this->parse('submission_listing', 'submission_block', true);
        }
    }
    else {
        $submissionList = $studentSubHandler->getByBoxID($this->arg[0]);

        if (is_array($submissionList)) {
            foreach($submissionList as $line) {

				/* If already marked display mark, else provide link to return mark */
                $return = $returnHandler->getByUser($line['uid'], $this->arg[0]);

				if (is_array($return)) {
					$mark = $return[0]['mark'];
				}
				else {
					$mark = "<a href=\"/submission/returnWork/".$this->arg[0]."/".$line['uid']."\">Mark</a>";
				}

                $output = array(
					'OWNER' 	=> $userHandler->formatName($line,'{lastname}, {firstname}'),
                    'OWNER_ID'      => $line['uid'],
                    'SUB_ID'        => $line['id'],
                    'OWNER_ESCAPED' => addslashes($userHandler->formatName($line,'{lastname}, {firstname}')),
                    'SUBMITTED'     => $line['date'],
                    'COLOUR'        => $line['late']?'#ff0000':'#000000',
                    'MARK'          => $return[0]['mark'],
                    'ON_OFF'        => ++$i%2?'':'dark',
            	);

				$this->setVar($output);
				$this->parse('download_action_block', 'download_action');
				$this->parse('mark_action_block', 'mark_action', false);
				$this->parse("submission_listing","submission_block",true);
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
?>
