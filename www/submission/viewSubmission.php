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

    $this->setVar('ASSDESC', scrubTEXT($subBox['name'])." - ".scrubTEXT($subBox['description']));

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

                $output = array(	'OWNER' 	=> formatName($line,'{lastname}, {firstname}'),
                                        'OWNER_ID' 	=> $line['uid'],
                                        'OWNER_ESCAPED' => addslashes(formatName($line,'{lastname}, {firstname}')),
                                        'DATE'		=> $line['date'],
                                        'VIEW'		=> 'viewSubmission.php?view='.$line['id'],
                                        'SAVE'		=> 'viewSubmission.php?view='.$line['id'].'&save=1',
                                        'DELETE'	=> 'viewSubmission.php?delete='.$line['id']."&box_id=$box_id",
                                        'COLOUR'	=> $line['late']?'#ff0000':'#000000',
                                        'MARKED'	=> $line['marked']?'<img src="/images/greentick.gif" width="13" height="12">':'<img src="/images/emptybox.gif" width="13" height="12">',
                                        'RETURN'	=> $line['marked']?'':"<a href=\"returnWork.php?box_id=$box_id&sid=".$line['uid']."\">Return</a>",
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
                    'VIEW'          => 'viewSubmission.php?view='.$line['id'],
                    'SAVE'          => 'viewSubmission.php?view='.$line['id'].'&save=1',
                    'DELETE'        => 'viewSubmission.php?delete='.$line['id']."&box_id=$box_id",
                    'COLOUR'        => $line['late']?'#ff0000':'#000000',
                    'MARKED'        => $line['marked']?'<img src="/images/greentick.gif" width="13" height="12">':'<img src="/images/emptybox.gif" width="13" height="12">',
                    'RETURN'        => $line['marked']?'':"<a href=\"returnWork.php?box_id=$box_id&sid=".$line['uid']."\">Return</a>",
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



/*

    function sendWork($view, $save) {
        //sends students submission to owner of submission box only

        $result = $this->checkPerms($view);

        if($save)
            $attach = "attachment;";
        else
            $attach = "";

        //all checks completed.. continue with file send
        $filename = SUBMISSION_directory . "/" . $view . ".dat";

        //send headers
        header ("Content-Type: application/octet-stream");
        header ("Content-Disposition: $attach filename=\"".$result['lastname']."-".$result['realname']."\"");
        header ("Content-Length: ".filesize($filename));
        readfile($filename);

    }

    function deleteWork($id) {
        //delete a student submission to a box so they can resubmit

        $this->checkPerms($id);

        //delete from database
        $query = "DELETE FROM student_submission WHERE student_submission.id = '$id'";
        $this->db->query($query);

        //all checks completed.. continue with file delete
        $filename = SUBMISSION_directory . "/" . $id . ".dat";
        unlink($filename);

    }

    function checkPerms($id) {
        //check user has access to student submission

        //check if user has rights to view file and get file details and username for appending to save
        $query = "SELECT student_submission.realname, user.lastname, user.title, user.firstname
			FROM submission_box, student_submission, user 
			WHERE student_submission.id = '$id' AND student_submission.box_id = submission_box.id 
			AND submission_box.owner = '".$_SESSION['id']."' AND student_submission.owner = user.id";    	

        $this->db->query($query);
        if($this->db->countRows() > 0)
            $result = $this->db->getNextArray();
        else {
            error(44);
        }

        return $result;
    }

    function showPage($box_id) {
        $this->t_body = new Template(TPL_directory);
        $this->t_body->set_file("tplBODY", "submission/viewSub.htm");
        $this->t_body->set_var("TITLE", HEADING." - Student Submissions");
        $this->t_body->set_block("tplBODY", "submitted_block", "submission_listing");
        $this->t_body->set_block("submitted_block", "mark", "mark_block");
        $this->setSubmissions($box_id);

        include_once("../include/page.php");

        $this->t_body->pparse("finaloutput", "tplBODY");
    }
}

$myPage = new ViewSubmissionPage();

if(isset($_GET['delete']) && $_GET['delete']) {
    $myPage->deleteWork($_GET['delete']);
}

if(isset($_GET['box_id']) && $_GET['box_id']) {
    $myPage->showPage($_GET['box_id']);
}
else if(isset($_GET['view']) && $_GET['view']) {
    $myPage->sendWork($_GET['view'], $_GET['save']);
}*/
?>
