<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionStudent.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionReturn.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';

if(is_numeric($this->arg[0])) {

    $this->setFile(TEMPLATE_content, 'submission/viewSubmission.html');
    $this->setTitle('Submission Box');
	$this->setTab('submission');
    $this->setBlock(TEMPLATE_content, 'submitted_block', 'submission_listing');
    $this->setBlock('submitted_block', 'mark', 'mark_block');

    $submissionHandler = new Submission;
    $studentSubHandler = new SubmissionStudent;
	$returnHandler = new SubmissionReturn;

    $subBox = $submissionHandler->getByID($this->arg[0]);

    if($subBox['owner'] != $_SESSION['id'] ) {
        error(17);
    }

    $submissionHandler->logPageView($this->arg[0]);

    $this->setVar('ASSDESC', scrubTEXT($subBox['categoryName'])." - ".scrubTEXT($subBox['description']));
	 $this->setVar('CATEGORY_ID', $subBox['cid']);
    $this->setVar('BOX_ID', $subBox['id']);

    /* Get full student list of class to show those who have not submitted */
    if($subBox['code'] && SYNERGY_enable) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/synergetic-class.php';
        //if synergy code get synergy students
        $synHandler = new SynergeticClass();
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

            if(is_array($studentSub)) {

				/* If already marked display mark, else provide link to return mark */
				if($studentSub['marked']) {
					$return = $returnHandler->getByUser($user['uid'], $this->arg[0]);
					$mark = $return[0]['mark'];
				}
				else
					$mark = "<a href=\"/submission/returnWork/".$this->arg[0]."/".$user['uid']."\">Mark</a>";

                $output = array(		'OWNER' 	=> formatName($user,'{lastname}, {firstname}'),
                                        'OWNER_ID' 	=> $user['uid'],
										'SUB_ID'	=> $studentSub['id'],
                                        'OWNER_ESCAPED' => addslashes(formatName($student,'{lastname}, {firstname}')),
                                        'DATE'		=> $studentSub['date'],
                                        'DELETE'	=> '/submission/viewSubmissionForm.php?delete='.$studentSub['id']."&box_id=" . $this->arg[0],
                                        'COLOUR'	=> $studentSub['late']?'#ff0000':'#000000',
                                        'RETURN'	=> $mark,
                                        'ON_OFF'	=> ++$i%2?'':'dark',
                );
                $this->setVar($output);
                $this->parse('mark_block', 'mark', false);

            }
            else {

                $output = array(		'OWNER' 	=> formatName($user,'{lastname}, {firstname}'),
                                        'OWNER_ID' 	=> $user['uid'],
                                        'OWNER_ESCAPED' => addslashes(formatName($student,'{lastname}, {firstname}')),
                                        'mark_block'    => '<td colspan="6">&nbsp;</td>',
                                        'DATE'		=> '',
										'COLOUR'	=> '',
                                        'RETURN'	=> '',
                                        'ON_OFF'	=> ++$i%2?'':'dark',
                );

                $this->setVar($output);

            }

            $this->parse("submission_listing","submitted_block",true);
        }
    }
    else {

        $submissionList = $studentSubHandler->getByBoxID($this->arg[0]);

        if(is_array($submissionList)) {
            foreach($submissionList as $line) {

				/* If already marked display mark, else provide link to return mark */
				if($studentSub['marked']) {
					$return = $returnHandler->getByUser($line['uid'], $this->arg[0]);
					$mark = $return['mark'];
				}
				else
					$mark = "<a href=\"/submission/returnWork/".$this->arg[0]."/".$line['uid']."\">Mark</a>";

                $output = array(	'OWNER' 	=> formatName($line,'{lastname}, {firstname}'),
                    'OWNER_ID'      => $line['uid'],
					'SUB_ID'		=> $line['id'],
                    'OWNER_ESCAPED' => addslashes(formatName($line,'{lastname}, {firstname}')),
                    'DATE'          => $line['date'],
                    'COLOUR'        => $line['late']?'#ff0000':'#000000',
                    'RETURN'        => $mark,
                    'ON_OFF'        => ++$i%2?'':'dark',
            );

            $this->setVar($output);
            $this->parse('mark_block', 'mark', false);
            $this->parse("submission_listing","submitted_block",true);

            }
        }
    }

}
else
    error(2);

?>
