<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionReturn.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';

$subHandler = new Submission;
$retHandler = new SubmissionReturn();
$catHandler = new Category();
$groupHandler = new Group;
$userHandler = new User;

if($this->arg[0] == 'export' && is_numeric($this->arg[1])) {
    
    
    if(!$groupHandler->isStaff() )
            error(19);
    
    
	$category = $catHandler->getByID($this->arg[1]);
    
	/* Get full student list of class  */
    if($category['code'] && SYNERGY_enable) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/synergetic-class.php';
        //if synergy code get synergy students
        $synHandler = new SynergeticClass();
        $studentList = $synHandler->getStudents($category['code']);

		for($x=0; is_array($studentList[$x]);$x++){
			$user = $userHandler->getBySynergy($studentList[$x]['synergy_id']);
			$studentList[$x]['uid'] = $user['uid'];
		}

    }
    else {
        //Retrieve student list from group membership
        require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
        $groupHandler = new Group;
        $studentList = $groupHandler->getMemberList($this->arg[1]);
    }



	$subList = $subHandler->getAllForUserCategory($this->arg[1], $_SESSION['id']);

	if(is_array($subList)) {
        $csv = 'Synergetic ID,Surname,Firstname,';
		foreach($subList as $sub) {
            $csv .= scrubTEXT($sub['description']).',';
		}
	}
    $csv .= "\n";

	if(is_array($studentList)) {
		foreach($studentList as $student) {
            $csv .= $student['uid'].','.scrubTEXT($student['lastname']).','.scrubTEXT($student['firstname']).',';

			if(is_array($subList)) {
				foreach($subList as $sub) {
                    $return = $retHandler->getByUser($student['uid'],$sub['id']);
                    $csv .= scrubTEXT($return[0]['mark']).',';
				}
			}   
            $csv .="\n";
		}
	}
    
    $filename = preg_replace('/[^0-9a-zA-Z()_-]/','_', $category['name']);

    header ("Pragma: ");
    header ("Cache-control: ");
    header ("Content-Type: text/csv");
    header ("Content-Disposition: attachment; filename=\"".$filename.".csv\"");
    echo $csv;

    exit;
}
else if(is_numeric($this->arg[0])) {
	$this->setFile(TEMPLATE_content, 'submission/markSheet.html');
	$this->setTitle('Class Mark Sheet');
	$this->setTab('submission');
	
	$this->setBlock(TEMPLATE_content, 'sub_head', 'sub_head_block');
	$this->setBlock(TEMPLATE_content, 'student_list', 'student_list_block');
	$this->setBlock('student_list', 'mark', 'mark_block');



    if(!$groupHandler->isStaff() )
            error(19);


	$category = $catHandler->getByID($this->arg[0]);

	if($category['code'] != "")
			$this->setVar('CATEGORY', $category['name'].' ('.$category['code'].')');
	else
			$this->setVar('CATEGORY', $category['name']);

	$this->setVar('CID', $category['id']);

	/* Get full student list of class  */
    if($category['code'] && SYNERGY_enable) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/synergetic-class.php';
        //if synergy code get synergy students
        $synHandler = new SynergeticClass();
        $studentList = $synHandler->getStudents($category['code']);

		for($x=0; is_array($studentList[$x]);$x++){
			$user = $userHandler->getBySynergy($studentList[$x]['synergy_id']);
			$studentList[$x]['uid'] = $user['uid'];
		}

    }
    else {
        //Retrieve student list from group membership
        require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
        $groupHandler = new Group;
        $studentList = $groupHandler->getMemberList($this->arg[0]);
    }



	$subList = $subHandler->getAllForUserCategory($this->arg[0], $_SESSION['id']);

	if(is_array($subList)) {
		foreach($subList as $sub) {
			$output = array( 'BOX_ID'	=> $sub['id'],
							'SUB_NAME'	=> scrubTEXT($sub['description']),
							'SUB_TYPE'	=> scrubTEXT($sub['typeName']));
			$this->setVar($output);
			$this->parse('sub_head_block','sub_head',true);
		}
	}
	else {
		$this->setVar('sub_head_block','');
	}

	if(is_array($studentList)) {
		foreach($studentList as $student) {

			$output = array('SURNAME'	=> scrubTEXT($student['lastname']),
							'FIRSTNAME'	=> scrubTEXT($student['firstname']),
							'UID'		=> $student['uid'],
							'ON_OFF'    => ++$i%2?'':'dark',);
			$this->setVar($output);

			$this->setVar('mark_block','');

			if(is_array($subList)) {
				foreach($subList as $sub) {
					$return = $retHandler->getByUser($student['uid'],$sub['id']);
					$output = array('MARK'		=> scrubTEXT($return[0]['mark']),
									'BOX_ID'	=> $sub['id'],
							);
					$this->setVar($output);
					$this->parse('mark_block','mark',true);
				}
			}
			else {
				$this->setVar('mark_block','');
			}

			$this->parse('student_list_block', 'student_list',true);
		}
	}
	else
		$this->setVar('student_list_block', '');


}
else
	error(2);

?>
