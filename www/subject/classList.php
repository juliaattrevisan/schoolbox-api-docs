<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//ClassList object..displays class list with most recent visit 

require_once 'include/permission.php';
require_once '../library/category.php';
require_once '../library/synergetic-class.php';
require_once '../library/user.php';


if(isStaff($_SESSION['gid']))
{
	$userHandler = new User;
	$categoryHandler = new Category;
	$synergeticClass = new SynergeticClass;

	
	$this->setFile(TEMPLATE_content, 'class/classList.htm');
	$this->setBlock(TEMPLATE_content, 'student_list', 'student_list_block');
	$this->setTitle('Class List');

	$code = implode('/', $this->arg);
	$category = $categoryHandler->getByField("code", urldecode($code));

	if(is_array($category)) {
		$this->setVar("SUBJECTNAME", $category['name'] ." ($code)");	
	
		$classList = $synergeticClass->getStudents($code);
		if(is_array($classList)) {
			foreach($classList as $student) {
				
				$email = '';
				$emailaddr = '';
				$user = $userHandler->getBySynergy($student['synergy_id']);
			
				if(is_array($user)){
					$emailaddr = formatEmail($user);
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
										'ON_OFF' => ++$j%2? '' : 'dark',
										);
									
				$this->setVar($studentOutput);
				  
				$this->parse("student_list_block", "student_list", true);		
			}
		}
	}
	else 
		$this->setVar('student_list_block', '');
	
	
	$this->setVar("REPLYTO", formatEmail($_SESSION));
	$this->setVar("CLASSEMAIL", $classEmail);
	
}
else
{
	error(19);
}    

			


    


?>
