<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//ClassList object..displays class list with most recent visit

require_once 'include/permission.php';
require_once '../library/category.php';
require_once SITE_root . '/library/'.Cfg::get('external_type').'/ExternalClass.php';
require_once '../library/user.php';

if(isStaff($_SESSION['gid'])) {
	$userHandler = new User;
	$categoryHandler = new Category;
	$synergeticClass = new ExternalClass;


	$this->setFile(TEMPLATE_content, 'class/classList.htm');
	$this->setBlock(TEMPLATE_content, 'student_list', 'student_list_block');
    $this->setBlock('student_list', 'medical', 'medical_block');
    $this->setBlock('student_list', 'special', 'special_block');
    $this->setBlock('student_list', 'eportfolio', 'eportfolio_block');

	$this->setBlock('student_list', 'surname_with_link', 'surname_with_link_block');
	$this->setBlock('student_list', 'surname_without_link', 'surname_without_link_block');
	$this->setBlock('student_list', 'firstname_with_link', 'firstname_with_link_block');
	$this->setBlock('student_list', 'firstname_without_link', 'firstname_without_link_block');

	$this->setTitle('Class List');

	$code = urldecode(implode('/', $this->arg));

	if (stristr($code,',')) {
		$codeArray = explode(',',$code);
		$category = $categoryHandler->getByCode($codeArray[0]);
	}
	else {
		$category = $categoryHandler->getByCode($code);
	}

	if (is_array($category)) {
		$this->setVar("SUBJECTNAME", $category['name'] ." ($code)");

		$classList = $synergeticClass->getStudents($code);

		if (is_array($classList)) {
			foreach($classList as $student) {

				$email = '';
				$emailaddr = '';

				$user = $userHandler->getBySynergy($student['synergy_id']);

				if(is_array($user)){
					$emailObj = new Email();
					$emailaddr = $emailObj->formatEmailFromArray($user);

					if($emailaddr){
						$emaillink = "<a href=\"".$_SESSION['mailhandle'].$emailaddr."\">Email</a>";
						$classEmail .= $emailaddr.", ";
					}
				}

				$lastVisit = $categoryHandler->lastVisit($category['id'], $user['uid']);

				$studentOutput = array(	'SURNAME' 	=> $student['lastname'],
										'FIRSTNAME' => $student['firstname'],
										'YEARLEVEL' => $student['yearlevel'],
										'HOUSE' 	=> $student['house'],
										'LASTVISIT' => $lastVisit?$lastVisit:'Never',
										'ID'		=> $user['uid'],
										'SYNERGYID'	=> $student['synergy_id'],
										'EMAIL'		=> $emailaddr?$emaillink:'',
										'EMAIL_ADDRESS'=> $emailaddr,
                                        'MEDICAL_ALERT' => $student['StudentMedicalAlert'],
										'ON_OFF' => ++$j%2? '' : 'dark',
										);

				$this->setVar($studentOutput);

				if ($student['StudentMedicalAlert']) {
                    $this->parse('medical_block','medical');
				}
                else {
                    $this->setVar('medical_block','');
				}

                if ($student['StudentSpecialNeedsFlag']) {
                    $this->parse('special_block','special');
				}
                else {
                    $this->setVar('special_block','');
				}

				/* If the user has an enabled eportfolio, enable link
				 */
                if ($user['eportfolio_enabled']) {
                    $this->parse('eportfolio_block', 'eportfolio');
				}
                else {
                    $this->setVar('eportfolio_block', '');
				}
                
                /* Parse out the name blocks: if they were found in Schoolbox,
				 * give the names links - otherwise leave them unlinked
				 */
				if (is_array($user)) {
					$this->parse('surname_with_link_block', 'surname_with_link');
					$this->setVar('surname_without_link_block', '');

					$this->parse('firstname_with_link_block', 'firstname_with_link');
					$this->setVar('firstname_without_link_block', '');
				}
				else {
					$this->parse('surname_without_link_block', 'surname_without_link');
					$this->setVar('surname_with_link_block', '');

					$this->parse('firstname_without_link_block', 'firstname_without_link');
					$this->setVar('firstname_with_link_block', '');
				}
                
				$this->parse("student_list_block", "student_list", true);
			}
		}
		else {
			$this->setVar('student_list_block', '');
		}
	}
	else
		$this->setVar('student_list_block', '');

	$emailObj = new Email();
	$email = $emailObj->formatEmailFromArray($_SESSION);

	$this->setVar("REPLYTO", $email);
	$this->setVar("CLASSEMAIL", $classEmail);

}
else {
	error(19);
}
?>
