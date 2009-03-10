<?php

require_once '../library/user.php';
require_once 'include/permission.php';

//display user description page
if(is_numeric($this->arg[0])) {
	$this->setFile(TEMPLATE_content, 'user/detail.html');
	$this->setTitle('Address Book');

	$this->setBlock(TEMPLATE_content, "portrait", "portrait_block");
	$this->setBlock(TEMPLATE_content, "school_links", "school_links_block");
	$this->setBlock(TEMPLATE_content, "timetable_link", "timetable_link_block");
	$this->setBlock(TEMPLATE_content, "due_work", "due_work_block");
	$this->setBlock(TEMPLATE_content, "helpdesk", "helpdesk_block");
	$this->setBlock(TEMPLATE_content, "student_synergy_form", "student_synergy_form_block");
	$this->setBlock(TEMPLATE_content, "student_synergy_tutor", "student_synergy_tutor_block");
	$this->setBlock(TEMPLATE_content, "contact_synergy", "contact_synergy_block");
	$this->setBlock('contact_synergy', "contact_details", "contact_details_block");
	$this->setBlock(TEMPLATE_content, "medical", "medical_block");
	$this->setBlock(TEMPLATE_content, 'group_management', 'group_management_block');
	$this->setBlock('group_management', 'group', 'group_block');

	$userHandler = new User;
	
	$user = $userHandler->getByID($this->arg[0]);
	
	//Format email address link
	$emailAddress = formatEmail($user);
	if($emailAddress) {
			if(SITE_location == 'wenona')
				$email = '<a href="'.$_SESSION['mailhandle']."$emailAddress\">Send Email</a>";
			else
				$email = '<a href="'.$_SESSION['mailhandle']."$emailAddress\">$emailAddress</a>";
	}
	
	
	$outputUser = array('NAME' 			=> formatName($user),
						'ID'			=> $this->arg[0],
						'CAMPUS_ID'		=> $user['campus'],
						'EMAIL_ADDRESS' => $emailAddress,
						'EMAIL'			=> $email,
						'POSITION'		=> $user['gname'],
						'HOMEPAGE'		=> '',
						'SYNERGY_ID'	=> $user['synergy_id'],
						);
						
		
	$this->setVar($outputUser);
		
	//advanced synergetic information
	if(SYNERGY_enable)
	{
		require_once '../library/synergetic-user.php';

		$synUserHandler = new SynergeticUser;
		
		//only allow staff to see student information or parent
		if(isStaff($_SESSION['gid']) || isParentOfChild($user['uid']))
		{
			$this->parse("portrait_block", "portrait");		
			$this->parse("timetable_link_block", "timetable_link");
			
			if(isStudent($user['group_id']))
			{
				$this->parse("due_work_block", "due_work");	
				
				if(is_dir(HELPDESK_location))
					$this->parse("helpdesk_block", "helpdesk");	
				else
					$this->setVar("helpdesk_block", "");
						
				$synInfo = $synUserHandler->getStudent($user['synergy_id']);
			
				if($synInfo['StudentFormStaffName'] && $synInfo['TeacherFormID']) {
					$form = $userHandler->getByField('synergy_id', $synInfo['TeacherFormID']);
					if($formEmail = formatEmail($form))
						$formTeacher = '<a href="'.$_SESSION['mailhandle'].$formEmail.'">'.scrubTEXT($synInfo['StudentFormStaffName']).'</a>';
					else
						$formTeacher = scrubTEXT($synInfo['StudentFormStaffName']);
				}
			
				if($synInfo['StudentTutorName'] && $synInfo['TeacherTutorID']) {
					$tutor = $userHandler->getByField('synergy_id', $synInfo['TeacherTutorID']);
					if($tutorEmail = formatEmail($tutor))
						$tutorTeacher = '<a href="'.$_SESSION['mailhandle'].$tutorEmail.'">'.scrubTEXT($synInfo['StudentTutorName']).'</a>';
					else
						$tutorTeacher = scrubTEXT($synInfo['StudentTutorName']);
				}
				
				$output = array('TUTOR_GROUP' 			=> scrubTEXT($synInfo['StudentTutorDescription']),
								'TUTOR'					=> $tutorTeacher,
								'YEAR_LEVEL'			=> scrubTEXT($synInfo['StudentYearLevel']),
								'HOUSE'					=> scrubTEXT($synInfo['StudentHouseDescription']),
								'HOH'					=> scrubTEXT($synInfo['StudentHeadOfHouse']),
								'FORM'					=> scrubTEXT($synInfo['StudentForm']),
								'FORM_DESCRIPTION'		=> scrubTEXT($synInfo['FormDescription']),
								'FORM_STAFF'			=> $formTeacher,
								'LOCKER'				=> scrubTEXT($synInfo['StudentLockerBook']),
								'BIRTHDATE'				=> date('jS \o\f F, Y', $synInfo['StudentBirthDate']),
								'EMERGENCY_NAME'		=> scrubTEXT($synInfo['StudentEmergencyName']),
								'EMERGENCY_PHONE'		=> scrubTEXT($synInfo['StudentEmergencyPhone']),
								'EMERGENCY_ALT_PHONE'	=>scrubTEXT($synInfo['StudentEmergencyPhoneAlt']),
								'MEDICAL_NOTE'			=> scrubTEXT($synInfo['StudentMedicalAlert']),
								);
				$this->setVar($output);
				
				$studentContactList = $synUserHandler->getStudentContactList($user['synergy_id']);
				if (is_array($studentContactList)) {
					foreach ($studentContactList as $studentContact) {
						if ($studentContact['StudentContactID'] == $_SESSION['sid'] || $studentContact['StudentContactSpouseID'] == $_SESSION['sid'] || isStaff($_SESSION['gid']) || $_SESSION['su']) {
							$j++;
							if($studentContact['StudentContactDefaultEmail'])
								$contactName = '<a href="'.$_SESSION['mailhandle'].$studentContact['StudentContactDefaultEmail'].'">'. 
									scrubTEXT($studentContact['StudentContactTitle'].' '.$studentContact['StudentContactPreferred'].' '.$studentContact['StudentContactSurname']).'</a>';
							else
								$contactName = scrubTEXT($studentContact['StudentContactTitle'].' '.$studentContact['StudentContactPreferred'].' '.$studentContact['StudentContactSurname']);
								
							
							if($studentContact['StudentContactSpouseDefaultEmail'])
								$spouseName = '<a href="'.$_SESSION['mailhandle'].$studentContact['StudentContactSpouseDefaultEmail'].'">'. 
									scrubTEXT($studentContact['StudentContactSpouseTitle'].' '.$studentContact['StudentContactSpousePreferred'].' '.$studentContact['StudentContactSpouseSurname']).'</a>';
							else
								$spouseName = scrubTEXT($studentContact['StudentContactSpouseTitle'].' '.$studentContact['StudentContactSpousePreferred'].' '.$studentContact['StudentContactSpouseSurname']);
			
							$output = array('CONTACT_NAME'			=> scrubTEXT($studentContact['StudentContactTitle'].' '.
																	$studentContact['StudentContactPreferred'].' '.$studentContact['StudentContactSurname']),
											'CONTACT_ADDRESS'		=> scrubTEXT($studentContact['StudentContactAddress1']).'<br>'.scrubTEXT($studentContact['StudentContactSuburb'].', '.
																	$studentContact['StudentContactState'].' '.$studentContact['StudentContactPostCode']),
											'CONTACT_HOME_PHONE' 	=> scrubTEXT($studentContact['StudentContactPhoneActual']),
											'CONTACT_MOBILE_PHONE' 	=> scrubTEXT($studentContact['StudentContactMobilePhone']),
											'CONTACT_OCCUP_PHONE'	=> scrubTEXT($studentContact['StudentContactOccupPhone']),
											'CONTACT_EMAIL'			=> $studentContact['StudentContactDefaultEmail'],
											'CONTACT_NAME_EMAIL'	=> $contactName,
											'SPOUSE_NAME'			=> scrubTEXT($studentContact['StudentContactSpouseTitle'].' '.$studentContact['StudentContactSpousePreferred'].' '.
																	$studentContact['StudentContactSpouseSurname']),
											'SPOUSE_MOBILE_PHONE'	=> scrubTEXT($studentContact['StudentContactSpouseMobilePhone']),
											'SPOUSE_OCCUP_PHONE'	=> scrubTEXT($studentContact['StudentContactSpouseOccupPhone']),
											'SPOUSE_EMAIL'			=> $studentContact['StudentContactSpouseDefaultEmail'],
											'SPOUSE_NAME_EMAIL'		=> $spouseName,
												
											);
							$this->setVar($output);
							$this->parse('contact_details_block', 'contact_details', true);						
						
						}
					}	
				}
				
				if (!$j)
					$this->setVar('contact_details_block', '');
				
				$this->parse("contact_synergy_block", "contact_synergy");
				
				if(SITE_location == 'wenona')
					$this->parse("school_links_block","school_links");
				else
					$this->setVar("school_links_block","");
				
				if($synInfo['StudentYearLevel'] < 7) {
					$this->parse('student_synergy_form_block', 'student_synergy_form');
					$this->setVar('student_synergy_tutor_block','');
				}
				else {
					$this->parse('student_synergy_tutor_block','student_synergy_tutor');
					$this->setVar('student_synergy_form_block','');
				}
				
				if(trim($synInfo['StudentMedicalAlert']))
					$this->parse('medical_block', 'medical');
				else 
					$this->setVar('medical_block', '');
	
			}
			else
			{
				$blank = array ('student_synergy_tutor_block'	=> '',
								'student_synergy_form_block'	=> '',
								'medical_block'					=> '',
								'contact_synergy_block'			=> '',
								'due_work_block'				=> '',
								'helpdesk_block'				=> '',
								'school_links_block'			=> '',
								);
				$this->setVar($blank);
			}
		}
		else
		{
			$blank = array ('contact_synergy_block'			=> '',
							'student_synergy_tutor_block'	=> '',
							'student_synergy_form_block'	=> '',
							'medical_block'					=> '',
							'portrait_block'				=> '',
							'timetable_link_block'			=> '',
							'contact_synergy_block'			=> '',
							'due_work_block'				=> '',
							'helpdesk_block'				=> '',
							'school_links_block'			=> '',
							);
			$this->setVar($blank);
		}
	}
	else
	{
		$blank = array ('contact_synergy_block'			=> '',
						'student_synergy_tutor_block'	=> '',
						'student_synergy_form_block'	=> '',
						'medical_block'					=> '',
						'portrait_block'				=> '',
						'timetable_link_block'			=> '',
						'contact_synergy_block'			=> '',
						'due_work_block'				=> '',
						'helpdesk_block'				=> '',
						'school_links_block'			=> '',
						);
		$this->setVar($blank);		
	}
	
	//display list of groups owner is in charge of so they can add the user
	$query = "SELECT sid, category.name FROM userAccess, category 
	WHERE service = ".HOMEPAGESERVICE." AND sid = category.id AND uid = '".$_SESSION['id']."' AND type = '3'"; 
	
	$this->db->query($query);
	if($this->db->countRows() > 0 && $_SESSION['id'] != $id)
	{
		while($group = $this->db->getNextArray())
		{
			$outputGroup = array('GROUP_ID'	=> $group['sid'],
								'GROUP_NAME'=> $group['name'],
								);
			$this->setVar($outputGroup);
			$this->parse('group_block', 'group', true);
		}		
		$this->setVar('HOMEPAGE_SERVICE', HOMEPAGESERVICE);
		
		$this->parse('group_management_block','group_management');
	}
	else
		$this->setVar('group_management_block', '');
}
else {
	$this->setFile(TEMPLATE_content, 'user/search.html');
	$this->setTitle('School Directory');
}

?>