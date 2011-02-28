<?php

require_once '../library/user.php';
require_once '../library/group.php';
require_once 'include/permission.php';

//display user description page
if(is_numeric($this->arg[0])) {
	$this->setTitle('Address Book');

	$userHandler = new User;

    $groupHandler = new Group;

	$user = $userHandler->getByID($this->arg[0]);

    if(!is_numeric($user['uid']))
        error(31);

    if($groupHandler->isStudent($user['uid'])) {
        $this->setFile(TEMPLATE_content, 'user/student.html');

        $this->setBlock(TEMPLATE_content, "portrait", "portrait_block");
        $this->setBlock(TEMPLATE_content, "school_links", "school_links_block");
        $this->setBlock(TEMPLATE_content, "timetable_link", "timetable_link_block");
        $this->setBlock(TEMPLATE_content, "due_work", "due_work_block");
        $this->setBlock(TEMPLATE_content, "helpdesk", "helpdesk_block");
        $this->setBlock(TEMPLATE_content, "eportfolio", "eportfolio_block");
        $this->setBlock(TEMPLATE_content, "student_synergy_form", "student_synergy_form_block");
        $this->setBlock(TEMPLATE_content, "student_synergy_tutor", "student_synergy_tutor_block");
        $this->setBlock(TEMPLATE_content, 'contact_synergy', 'contact_synergy_block');
        $this->setBlock('contact_synergy', 'edit_emergency', 'edit_emergency_block');
        $this->setBlock('contact_synergy', 'edit_contact', 'edit_contact_block');
        $this->setBlock('contact_synergy', 'contact_details', 'contact_details_block');
        $this->setBlock('contact_synergy', 'contact_alert', 'contact_alert_block');
        $this->setBlock('contact_details', 'spouse_details', 'spouse_details_block');
        $this->setBlock(TEMPLATE_content, 'medical', 'medical_block');



    }
    else if($groupHandler->isParent($user['uid'])) {
        $this->setFile(TEMPLATE_content, 'user/parent.html');
        $this->setBlock(TEMPLATE_content, 'parent_synergy_form', 'parent_synergy_form_block');
        $this->setBlock('parent_synergy_form', 'parent_spouse', 'parent_spouse_block');
    }
    else {
        $this->setFile(TEMPLATE_content, 'user/staff.html');
        $this->setBlock(TEMPLATE_content, "portrait", "portrait_block");
        $this->setBlock(TEMPLATE_content, "timetable_link", "timetable_link_block");
        $this->setBlock(TEMPLATE_content, "eportfolio", "eportfolio_block");
        $this->setBlock(TEMPLATE_content, "due_work", "due_work_block");
        $this->setBlock(TEMPLATE_content, "staff_synergy", "staff_synergy_block");
    }

        $blank = array ('contact_synergy_block'			=> '',
                        'edit_emergency_block'          => '',
                        'edit_contact_block'          => '',
                        'contact_details_block'         => '',
			'student_synergy_tutor_block'	=> '',
			'student_synergy_form_block'	=> '',
			'medical_block'					=> '',
                        'contact_alert_block'					=> '',
                        'spouse_details_block'                                => '',
			'portrait_block'				=> '',
			'timetable_link_block'			=> '',
                        'eportfolio_block'              => '',
			'contact_synergy_block'			=> '',
			'due_work_block'				=> '',
			'helpdesk_block'				=> '',
			'school_links_block'			=> '',
                        'parent_synergy_form_block'     => '',
                        'parent_spouse_block'           => '',
                        'staff_synergy_block'           => '',
						);
		$this->setVar($blank);

	//Format email address link
	$emailAddress = formatEmail($user);
	if($emailAddress) {
            //Dont display emails of students to parents
            if($groupHandler->isStudent($user['uid']) && $groupHandler->isParent()) {
                $email = '';
            }
            else {
                if(SITE_location == 'wenona')
                    $email = '<a href="'.$_SESSION['mailhandle']."$emailAddress\">Send Email</a>";
                else
                    $email = '<a href="'.$_SESSION['mailhandle']."$emailAddress\">$emailAddress</a>";
            }
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
		

		
        if($groupHandler->isParent($user['uid']) && !$groupHandler->isStudent()) {
            $parent = $synUserHandler->getParent($user['synergy_id']);

            if($parent['SpouseDirectoryIncludeFlag']) {
                $output = array('HOME_PHONE'    => $parent['StudentContactHomePhone'],
                                'MOBILE_PHONE'  => $parent['StudentContactMobilePhone'],
                                );
                   $this->setVar($output);

                if($parent['StudentContactSpouseLegalFullName'] && !$parent['StudentParentsSeparatedFlag']) {
                    $output = array('NAME'          => scrubTEXT($parent['StudentContactLegalFullName']),
                                    'SPOUSE_NAME'   => scrubTEXT($parent['StudentContactSpouseLegalFullName']),
                                    'SPOUSE_MOBILE' => $parent['StudentContactSpouseMobilePhone'],
                                    'SPOUSE_EMAIL'  => '<a href="'.$_SESSION['mailhandle'].$parent['StudentContactSpouseEmail'].'\">'.$parent['StudentContactSpouseEmail'].'</a>',
                                     );
                    $this->setVar($output);
                    $this->parse('parent_spouse_block', 'parent_spouse');
                }
                else {
                    $this->setVar('parent_spouse_block');
                }

                $this->parse('parent_synergy_form_block','parent_synergy_form');
           }
           else {
               $this->setVar('parent_synergy_form_block');
           }
        }
        else if( $groupHandler->isStudent($user['uid']) && ($groupHandler->isStaff() || $groupHandler->isParentOfChild($user['uid']) || $_SESSION['id'] == $user['uid'] )) {
            ////only allow staff to see student information or parent
            $this->parse("portrait_block", "portrait");


            $this->parse("timetable_link_block", "timetable_link");

            /* Allow students, staff and parents of the student to see information about
             * due work. Also allow the current user if they view themselves in the
             * search results.
             */
            if ($groupHandler->isParent() && SITE_location == 'shore') {
                $this->setVar('due_work_block', '');
            }
            else {
                $this->parse('due_work_block', 'due_work');
            }
 

            if(HELPDESK_enable == true)
                $this->parse('helpdesk_block', 'helpdesk');
            else
                $this->setVar('helpdesk_block', '');

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
                            'CONTACT_ALERT'             => scrubTEXT($synInfo['AlertMessage']),
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


                        if(trim($studentContact['StudentContactSpouseTitle']))
                            $this->parse('spouse_details_block', 'spouse_details');

                        $this->parse('contact_details_block', 'contact_details', true);

                    }
                }
            }

            if (!$j)
                $this->setVar('contact_details_block', '');

            if(SITE_location == 'wenona') {
                $this->parse('school_links_block','school_links');
                $this->parse('edit_emergency_block', 'edit_emergency');
                $this->parse('edit_contact_block', 'edit_contact');
            }

            $this->parse('contact_synergy_block', 'contact_synergy');

            if(trim($synInfo['StudentForm'])) {
                $this->parse('student_synergy_form_block', 'student_synergy_form');
                $this->setVar('student_synergy_tutor_block','');
            }
            else {
                $this->parse('student_synergy_tutor_block','student_synergy_tutor');
                $this->setVar('student_synergy_form_block','');
            }

            if(trim($synInfo['StudentMedicalAlert']))
                $this->parse('medical_block', 'medical');

           if(trim($synInfo['AlertMessage']))
                $this->parse('contact_alert_block', 'contact_alert');

       }
       else if($groupHandler->isStaff($user['uid']) && $groupHandler->isStaff()) {

            $this->parse("timetable_link_block", "timetable_link");

            $this->parse("portrait_block", "portrait");

             $synInfo = $synUserHandler->getStaff($user['synergy_id']);

             $this->setVar('EXTENSION', $synInfo['StaffExtension']);
             $this->setVar('MOBILE', $synInfo['StaffMobilePhone']);

             $this->parse('staff_synergy_block','staff_synergy');
       }

    }

    /* Display eportfolio link only if it's enabled
     */
    if ($user['eportfolio_enabled']) {
        $this->parse('eportfolio_block', 'eportfolio');
    }
    else {
        $this->setVar('eportfolio_block', '');
    }


}
else {
	error(2);
}

?>