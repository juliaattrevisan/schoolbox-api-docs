<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionStudent.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';

if(is_numeric($this->arg[0])) {

    $this->setFile(TEMPLATE_content, 'submission/viewSubmission.html');
    $this->setTitle('Submission Box');
    $this->setBlock(TEMPLATE_content, 'submitted_block', 'submission_listing');
    $this->setBlock('submitted_block', 'mark', 'mark_block');

    $submissionHandler = new Submission;
    $studentSubHandler = new SubmissionStudent;

    $subBox = $submissionHandler->getByID($this->arg[0]);

    if($subBox['owner'] != $_SESSION['id'] ) {
        error(17);
    }

    $submissionHandler->logPageView($this->arg[0]);

    $this->setVar('ASSDESC', scrubTEXT($subBox['categoryName'])." - ".scrubTEXT($subBox['description']));
	$this->setVar('CATEGORY_ID', $subBox['cid']);

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

            if($student['uid'])
                $studentSub = $studentSubHandler->getByUserID($this->arg[0], $student['uid']);
            else if($student['synergy_id'])
                $studentSub = $studentSubHandler->getBySynergyID($this->arg[0], $student['synergy_id']);
            else
                continue;

            if(is_array($studentSub)) {

                $output = array(	'OWNER' 	=> formatName($student,'{lastname}, {firstname}'),
                                        'OWNER_ID' 	=> $student['uid'],
                                        'OWNER_ESCAPED' => addslashes(formatName($student,'{lastname}, {firstname}')),
                                        'DATE'		=> $studentSub['date'],
                                        'VIEW'		=> '/submission/send.php?id='.$studentSub['id'].'&view=1',
                                        'SAVE'		=> '/submission/send.php?id='.$studentSub['id'],
                                        'DELETE'	=> '/submission/viewSubmissionForm.php?delete='.$studentSub['id']."&box_id=" . $this->arg[0],
                                        'COLOUR'	=> $studentSub['late']?'#ff0000':'#000000',
                                        'MARKED'	=> $studentSub['marked']?'<img src="/images/greentick.gif" width="13" height="12">':'<img src="/images/emptybox.gif" width="13" height="12">',
                                        'RETURN'	=> $studentSub['marked']?'':"<a href=\"/submission/returnWork/".$this->arg[0]."/".$studentSub['uid']."\">Return</a>",
                                        'ON_OFF'	=> ++$i%2?'':'dark',
                );
                $this->setVar($output);
                $this->parse('mark_block', 'mark', false);

            }
            else {

                if(!$student['uid'])
                    $user = $userHandler->getBySynergy($student['synergy_id']);

                $output = array(	'OWNER' 	=> formatName($student,'{lastname}, {firstname}'),
                                        'OWNER_ID' 	=> $user['id'],
                                        'OWNER_ESCAPED' => addslashes(formatName($student,'{lastname}, {firstname}')),
                                        'mark_block'    => '<td colspan="6">&nbsp;</td>',
                                        'DATE'		=> '',
                                        'MARKED'	=> '',
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
                $output = array(	'OWNER' 	=> formatName($line,'{lastname}, {firstname}'),
                    'OWNER_ID'      => $line['uid'],
                    'OWNER_ESCAPED' => addslashes(formatName($line,'{lastname}, {firstname}')),
                    'DATE'          => $line['date'],
                    'VIEW'          => '/submission/viewSubmissionForm.php?view='.$line['id'],
                    'SAVE'          => '/submission/viewSubmissionForm.php?view='.$line['id'].'&save=1',
                    'DELETE'        => '/submission/viewSubmissionForm.php?delete='.$line['id']."&box_id=" . $this->arg[0],
                    'COLOUR'        => $line['late']?'#ff0000':'#000000',
                    'MARKED'        => $line['marked']?'<img src="/images/greentick.gif" width="13" height="12">':'<img src="/images/emptybox.gif" width="13" height="12">',
                    'RETURN'        => $line['marked']?'':"<a href=\"/submission/returnWork/".$this->arg[0]."/".$line['uid']."\">Return</a>",
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
