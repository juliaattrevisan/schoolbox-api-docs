<?php

require_once '../library/user.php';
require_once '../library/group.php';
//require_once 'include/permission.php';

//display user description page
if (is_numeric($this->arg[0])) {
    $this->setTitle('Address Book');

    $userHandler = new User();
    $groupHandler = new Group();

    $user = $userHandler->getByID($this->arg[0]);

    if (!is_numeric($user['uid'])) {
        error(31);
    }

    if ($groupHandler->isStudent($user['uid'])) {
        $this->setFile(TEMPLATE_content, 'user/student.html');
        $this->setBlock(TEMPLATE_content, "portrait", "portrait_block");
        $this->setBlock(TEMPLATE_content, "school_links", "school_links_block");
        $this->setBlock(TEMPLATE_content, "timetable_link", "timetable_link_block");
        $this->setBlock(TEMPLATE_content, "due_work", "due_work_block");
        $this->setBlock(TEMPLATE_content, "helpdesk", "helpdesk_block");
        $this->setBlock(TEMPLATE_content, "eportfolio", "eportfolio_block");
        $this->setBlock(TEMPLATE_content, "boarder_leave", "boarder_leave_block");
        $this->setBlock(TEMPLATE_content, "student_synergy_form", "student_synergy_form_block");
        $this->setBlock(TEMPLATE_content, "student_synergy_tutor", "student_synergy_tutor_block");
        $this->setBlock(TEMPLATE_content, 'contact_synergy', 'contact_synergy_block');
        $this->setBlock('contact_synergy', 'edit_emergency', 'edit_emergency_block');
        $this->setBlock('contact_synergy', 'edit_contact', 'edit_contact_block');
        $this->setBlock('contact_synergy', 'contact_details', 'contact_details_block');
        $this->setBlock('contact_synergy', 'contact_alert', 'contact_alert_block');
        $this->setBlock('contact_details', 'spouse_details', 'spouse_details_block');
        $this->setBlock(TEMPLATE_content, 'medical', 'medical_block');
        $this->setBlock(TEMPLATE_content, 'subject', 'subject_block');
        $this->setBlock('subject', 'subject_row', 'subject_row_block');
        $this->setBlock('subject', 'subject_mail', 'subject_mail_block');
        $this->setBlock('subject_row', 'subject_item', 'subject_item_block');

    } else {
        if ($groupHandler->isParent($user['uid'])) {
            $this->setFile(TEMPLATE_content, 'user/parent.html');
            $this->setBlock(TEMPLATE_content, "eportfolio", "eportfolio_block");
            $this->setBlock(TEMPLATE_content, 'parent_synergy_form', 'parent_synergy_form_block');
            $this->setBlock('parent_synergy_form', 'parent_spouse', 'parent_spouse_block');
        } else {
            $this->setFile(TEMPLATE_content, 'user/staff.html');
            $this->setBlock(TEMPLATE_content, "portrait", "portrait_block");
            $this->setBlock(TEMPLATE_content, "timetable_link", "timetable_link_block");
            $this->setBlock(TEMPLATE_content, "eportfolio", "eportfolio_block");
            $this->setBlock(TEMPLATE_content, "due_work", "due_work_block");
            $this->setBlock(TEMPLATE_content, "staff_synergy", "staff_synergy_block");
        }
    }

    $blank = array(
        'contact_synergy_block' => '',
        'edit_emergency_block' => '',
        'edit_contact_block' => '',
        'contact_details_block' => '',
        'student_synergy_tutor_block' => '',
        'student_synergy_form_block' => '',
        'medical_block' => '',
        'contact_alert_block' => '',
        'spouse_details_block' => '',
        'portrait_block' => '',
        'timetable_link_block' => '',
        'eportfolio_block' => '',
        'boarder_leave_block' => '',
        'contact_synergy_block' => '',
        'due_work_block' => '',
        'helpdesk_block' => '',
        'school_links_block' => '',
        'parent_synergy_form_block' => '',
        'parent_spouse_block' => '',
        'staff_synergy_block' => '',
        'subject_block' => '',
        'subject_row_block' => '',
    );

    $this->setVar($blank);

    //Format email address link
    $emailObj = new Email();
    $emailAddress = $emailObj->formatEmailFromArray($user);

    /* Dont display emails of students to parents
      */
    if ($emailAddress) {
        if ($groupHandler->isStudent($user['uid']) && $groupHandler->isParent() && !$groupHandler->isParentOfChild($user['uid'])) {
            $email = '';
        } else {
            if (in_array(Cfg::get('site_location'), array('bialik', 'wenona')) && $groupHandler->isParent($user['uid']) && !$groupHandler->isStaff()) {
                //dont display parent emails to anyone but staff
                $email = '';
            } else {
                if (Cfg::get('site_location') == 'wenona') {
                    $email = '<a href="' . $_SESSION['mailhandle'] . "$emailAddress\">Send Email</a>";
                } else {
                    $email = '<a href="' . $_SESSION['mailhandle'] . "$emailAddress\">$emailAddress</a>";
                }
            }
        }
    }

    $userHandler = new User();

    $outputUser = array(
        'NAME' => $userHandler->formatName($user),
        'ID' => $this->arg[0],
        'CAMPUS_ID' => $user['campus'],
        'EMAIL_ADDRESS' => $emailAddress,
        'EMAIL' => $email,
        'POSITION' => $user['gname'],
        'HOMEPAGE' => '',
        'SYNERGY_ID' => $user['synergy_id'],
    );

    $this->setVar($outputUser);

    //advanced synergetic information
    if (Cfg::get('external_enable') && $user['synergy_id']) {

        $synUserHandler = new External_User();

        if ($groupHandler->isParent($user['uid']) && !$groupHandler->isStudent()) {
            $parent = $synUserHandler->getParent($user['synergy_id']);

            if ($parent['DirectoryIncludeFlag']) {
                $output = array(
                    'NAME' => Scrub::text($parent['Title'] . ' ' . $parent['Preferred'] . ' ' . $parent['Surname']),
                    'HOME_PHONE' => !$parent['SilentPhoneFlag'] ? $parent['HomePhone'] : '',
                    'MOBILE_PHONE' => $parent['MobilePhone'],
                );

                $this->setVar($output);

                if ($parent['SpouseDirectoryIncludeFlag'] && $parent['SpouseSurname']) {
                    $output = array(

                        'SPOUSE_NAME' => Scrub::text(
                            $parent['SpouseTitle'] . ' ' . $parent['SpousePreferred'] . ' ' . $parent['SpouseSurname']
                        ),
                        'SPOUSE_MOBILE' => $parent['SpouseMobilePhone'],
                        'SPOUSE_EMAIL' => '<a href="' . $_SESSION['mailhandle'] . $parent['SpouseEmail'] . '\">' . $parent['SpouseEmail'] . '</a>',
                    );

                    $this->setVar($output);
                    $this->parse('parent_spouse_block', 'parent_spouse');
                } else {
                    $this->setVar('parent_spouse_block');
                }

                $this->parse('parent_synergy_form_block', 'parent_synergy_form');
            } else {
                $this->setVar('parent_synergy_form_block');
            }
        } else {
            if ($groupHandler->isStudent($user['uid']) && ($groupHandler->isStaff() || $groupHandler->isParentOfChild( $user['uid']) || $_SESSION['id'] == $user['uid']) ) {
                ////only allow staff to see student information or parent
                $this->parse("portrait_block", "portrait");

                $this->parse("timetable_link_block", "timetable_link");

                /* Allow students, staff and parents of the student to see information about
                * due work. Also allow the current user if they view themselves in the
                * search results.
                */
                if ($groupHandler->isParent() && Cfg::get('site_location') == 'shore') {
                    $this->setVar('due_work_block', '');
                } else {
                    $this->parse('due_work_block', 'due_work');
                }

                if (HELPDESK_enable == true) {
                    $this->parse('helpdesk_block', 'helpdesk');
                } else {
                    $this->setVar('helpdesk_block', '');
                }

                $synInfo = $synUserHandler->getStudent($user['synergy_id']);

                if ($synInfo['StudentFormStaffName'] && $synInfo['TeacherFormID']) {
                    $form = $userHandler->getBySynergy($synInfo['TeacherFormID']);

                    $emailObj = new Email();
                    $formEmail = $emailObj->formatEmailFromArray($form);

                    if ($formEmail) {
                        $formTeacher = '<a href="' . $_SESSION['mailhandle'] . $formEmail . '">' . Scrub::text(
                            $synInfo['StudentFormStaffName']
                        ) . '</a>';
                    } else {
                        $formTeacher = Scrub::text($synInfo['StudentFormStaffName']);
                    }
                } else {
                    $formTeacher = $synInfo['StudentFormStaffName'];
                }

                if ($synInfo['StudentTutorName'] && $synInfo['TeacherTutorID']) {
                    $tutor = $userHandler->getBySynergy($synInfo['TeacherTutorID']);
                    $emailObj = new Email();
                    $tutorEmail = $emailObj->formatEmailFromArray($tutor);

                    if ($tutorEmail) {
                        $tutorTeacher = '<a href="' . $_SESSION['mailhandle'] . $tutorEmail . '">' . Scrub::text(
                            $synInfo['StudentTutorName']
                        ) . '</a>';
                    } else {
                        $tutorTeacher = Scrub::text($synInfo['StudentTutorName']);
                    }
                } else {
                    $tutorTeacher = $synInfo['StudentTutorName'];
                }

                if ($synInfo['StudentHeadOfHouse'] && $synInfo['HeadOfHouseID']) {
                    $hoh = $userHandler->getBySynergy($synInfo['HeadOfHouseID']);

                    $emailObj = new Email();
                    $hohEmail = $emailObj->formatEmailFromArray($hoh);

                    if ($hohEmail) {
                        $hohTeacher = '<a href="' . $_SESSION['mailhandle'] . $hohEmail . '">' . Scrub::text(
                            $synInfo['StudentHeadOfHouse']
                        ) . '</a>';
                    } else {
                        $hohTeacher = Scrub::text($synInfo['StudentHeadOfHouse']);
                    }
                } else {
                    $hohTeacher = $synInfo['StudentHeadOfHouse'];
                }

                if ($synInfo['StudentYearLevelCoordinator'] && $synInfo['YearLevelCoordID']) {
                    $coord = $userHandler->getBySynergy($synInfo['YearLevelCoordID']);

                    $emailObj = new Email();
                    $coordEmail = $emailObj->formatEmailFromArray($coord);

                    if ($coordEmail) {
                        $coordTeacher = '<a href="' . $_SESSION['mailhandle'] . $coordEmail . '">' . Scrub::text(
                            $synInfo['StudentYearLevelCoordinator']
                        ) . '</a>';
                    } else {
                        $coordTeacher = Scrub::text($synInfo['StudentYearLevelCoordinator']);
                    }
                } else {
                    $coordTeacher = $synInfo['StudentYearLevelCoordinator'];
                }


                $output = array(
                    'TUTOR_GROUP' => Scrub::text($synInfo['StudentTutorDescription']),
                    'TUTOR' => $tutorTeacher,
                    'YEAR_LEVEL' => Scrub::text($synInfo['StudentYearLevel']),
                    'HOUSE' => Scrub::text($synInfo['StudentHouseDescription']),
                    'HOH' => $hohTeacher,
                    'FORM' => Scrub::text($synInfo['StudentForm']),
                    'FORM_DESCRIPTION' => Scrub::text($synInfo['FormDescription']),
                    'FORM_STAFF' => $formTeacher,
                    'YCOORD' => $coordTeacher,
                    'LOCKER' => Scrub::text($synInfo['StudentLockerBook']),
                    'BIRTHDATE' => date('j F Y', $synInfo['StudentBirthDate']),
                    'EMERGENCY_NAME' => Scrub::text($synInfo['StudentEmergencyName']),
                    'EMERGENCY_PHONE' => Scrub::text($synInfo['StudentEmergencyPhone']),
                    'EMERGENCY_ALT_PHONE' => Scrub::text($synInfo['StudentEmergencyPhoneAlt']),
                    'EMERGENCY_NAME_2' => Scrub::text($synInfo['StudentEmergency2Name']),
                    'EMERGENCY_PHONE_2' => Scrub::text($synInfo['StudentEmergency2Phone']),
                    'EMERGENCY_ALT_PHONE_2' => Scrub::text($synInfo['StudentEmergency2PhoneAlt']),
                    'EMERGENCY_NAME_3' => Scrub::text($synInfo['StudentEmergency3Name']),
                    'EMERGENCY_PHONE_3' => Scrub::text($synInfo['StudentEmergency3Phone']),
                    'EMERGENCY_ALT_PHONE_3' => Scrub::text($synInfo['StudentEmergency3PhoneAlt']),
                    'MEDICAL_NOTE' => Scrub::text($synInfo['StudentMedicalAlert']),
                    'CONTACT_ALERT_MESSAGE' => Scrub::text($synInfo['AlertMessage']),
                    'STUDENT_TYPE' => $synUserHandler->determineBoarderType($synInfo['StudentBoarder']),
                );

                $this->setVar($output);
                $synClass = new External_Class;
                $catHandler = new Category;
                $i = 1;

                /*Output subject list */
                $subjectList = $synUserHandler->getSubjectList($user['synergy_id'], $user['uid']);

                if (is_array($subjectList)) {

                    $emailAll = '';

                    foreach ($subjectList as $subject) {

                        $category = $catHandler->getByCode($subject['code']);

                        if ((Cfg::get('show_all_subjects') == 1) || $category['id'] ) {

                            /* Fetch teacher names */
                            $teacherList = $synClass->getTeacherName($subject['code']);
                            $teacherOutput = array();

                            if (is_array($teacherList)) {
                                foreach ($teacherList as $teacherSynID) {
                                    $teacher = $userHandler->getBySynergyAndGroup(
                                        $teacherSynID, $groupHandler->getStaffGroupTypeIds()
                                    );
                                    if (is_array($teacher)) {
                                        $teacherName = $userHandler->formatName($teacher);

                                        $emailObj = new Email();
                                        $emailAddress = $emailObj->formatEmailFromArray($teacher);

                                        if ($emailAddress) {
                                            $teacherOutput[] = '<a href="' . $_SESSION['mailhandle'] . "$emailAddress\">$teacherName</a>";
                                            $emailAll .= $emailAddress . ",";
                                        } else {
                                            $teacherOutput[] = $teacherName;
                                        }
                                    }
                                }
                            }

                            $output = array(
                                'SUBJECT_NAME' => is_array($category) && $groupHandler->getPermission( $category['id']  ) >= READ_ACCESS ?
                                    '<a href="/homepage/' . $subject['code'] . '">' . Scrub::Text($category['name']) . '</a>' : Scrub::Text($subject['name']),
                                'SUBJECT_TEACHER_NAME' => implode(', ', $teacherOutput),
                                'SUBJECT_TEACHER_ID' => $teacherList[0],
                            );

                            $this->setVar($output);
                            $this->parse('subject_item_block', 'subject_item', true);

                            if ($i % 3 == 0) {
                                $this->parse('subject_row_block', 'subject_row', true);
                                $this->setVar('subject_item_block', '');
                            }
                            $i++;
                        }
                    }

                    if (($i - 1) % 3 != 0) {

                        /*for(;$i%3 > 0;$i++){
                            $this->setVar('subject_item_block','',true);
                        }*/

                        $this->parse('subject_row_block', 'subject_row', true);
                    }

                    /*Output email all subject teachers */
                    if ($groupHandler->isStaff()) {
                        $this->setVar("REPLYTO", $emailObj->formatEmailFromArray($_SESSION));
                        $this->setVar("EMAILALL", $emailAll);
                        $this->parse("subject_mail_block", "subject_mail");
                    } else {
                        $this->setVar('subject_mail_block', '');
                    }

                    $this->parse('subject_block', 'subject');

                }

                $studentContactList = $synUserHandler->getStudentContactList($user['synergy_id']);

                if (is_array($studentContactList)) {
                    foreach ($studentContactList as $studentContact) {

                        if ((int)$studentContact['StudentContactID'] == (int)$_SESSION['sid'] || $studentContact['StudentContactSpouseID'] == $_SESSION['sid'] || $groupHandler->isStaff() || $_SESSION['su']) {
                            $j++;

                            if ($studentContact['StudentContactDefaultEmail']) {
                                $contactName = '<a href="' . $_SESSION['mailhandle'] . $studentContact['StudentContactDefaultEmail'] . '">' . Scrub::text(
                                    $studentContact['StudentContactTitle'] . ' ' . $studentContact['StudentContactPreferred'] . ' ' . $studentContact['StudentContactSurname']
                                ) . '</a>';
                            } else {
                                $contactName = Scrub::text(
                                    $studentContact['StudentContactTitle'] . ' ' . $studentContact['StudentContactPreferred'] . ' ' . $studentContact['StudentContactSurname']
                                );
                            }

                            if ($studentContact['StudentContactSpouseDefaultEmail']) {
                                $spouseName = '<a href="' . $_SESSION['mailhandle'] . $studentContact['StudentContactSpouseDefaultEmail'] . '">' . Scrub::text(
                                    $studentContact['StudentContactSpouseTitle'] . ' ' . $studentContact['StudentContactSpousePreferred'] . ' ' . $studentContact['StudentContactSpouseSurname']
                                ) . '</a>';
                            } else {
                                $spouseName = Scrub::text(
                                    $studentContact['StudentContactSpouseTitle'] . ' ' . $studentContact['StudentContactSpousePreferred'] . ' ' . $studentContact['StudentContactSpouseSurname']
                                );
                            }

                            $output = array(
                                'CONTACT_NAME' => Scrub::text(
                                    $studentContact['StudentContactTitle'] . ' ' . $studentContact['StudentContactPreferred'] . ' ' . $studentContact['StudentContactSurname']
                                ),
                                'CONTACT_ADDRESS' => Scrub::text(
                                    $studentContact['StudentContactAddress1']
                                ) . '<br>' . Scrub::text(
                                    $studentContact['StudentContactSuburb'] . ', ' . $studentContact['StudentContactState'] . ' ' . $studentContact['StudentContactPostCode']
                                ),
                                'CONTACT_HOME_PHONE' => Scrub::text($studentContact['StudentContactPhoneActual']),
                                'CONTACT_MOBILE_PHONE' => Scrub::text($studentContact['StudentContactMobilePhone']),
                                'CONTACT_OCCUP_PHONE' => Scrub::text($studentContact['StudentContactOccupPhone']),
                                'CONTACT_EMAIL' => $studentContact['StudentContactDefaultEmail'],
                                'CONTACT_NAME_EMAIL' => $contactName,
                                'SPOUSE_NAME' => Scrub::text(
                                    $studentContact['StudentContactSpouseTitle'] . ' ' . $studentContact['StudentContactSpousePreferred'] . ' ' . $studentContact['StudentContactSpouseSurname']
                                ),
                                'SPOUSE_MOBILE_PHONE' => Scrub::text(
                                    $studentContact['StudentContactSpouseMobilePhone']
                                ),
                                'SPOUSE_OCCUP_PHONE' => Scrub::text($studentContact['StudentContactSpouseOccupPhone']),
                                'SPOUSE_EMAIL' => $studentContact['StudentContactSpouseDefaultEmail'],
                                'SPOUSE_NAME_EMAIL' => $spouseName,
                            );

                            $this->setVar($output);

                            if (trim($studentContact['StudentContactSpouseTitle']) || trim($studentContact['StudentContactSpousePreferred'])) {
                                $this->parse('spouse_details_block', 'spouse_details');
                            }
                            else {
                                $this->setVar('spouse_details_block','');
                            }

                            $this->parse('contact_details_block', 'contact_details', true);
                        }
                    }
                }

                if ($groupHandler->isStaff() || (Cfg::get('site_location') != 'mgs' && $groupHandler->isParentOfChild(
                    $user['uid']
                ))
                ) {
                    if (trim($synInfo['StudentMedicalAlert'])) {
                        $this->parse('medical_block', 'medical');
                    }

                    if (trim($synInfo['AlertMessage'])) {
                        $this->parse('contact_alert_block', 'contact_alert');
                    }
                }

                if (!$j) {
                    $this->setVar('contact_details_block', '');
                }

                if (Cfg::get('site_location') == 'wenona') {
                    $this->parse('school_links_block', 'school_links');
                    $this->parse('edit_emergency_block', 'edit_emergency');
                    $this->parse('edit_contact_block', 'edit_contact');
                }

                /* Display boarder leave for kings */
                if (Cfg::get('boarder_leave_enabled') == '1') {
                    if ($groupHandler->isStaff() || $_SESSION['id'] == $user['uid']) {
                        $this->parse('boarder_leave_block', 'boarder_leave');
                    }
                }

                $this->parse('contact_synergy_block', 'contact_synergy');

                if (trim($synInfo['StudentForm'])) {
                    $this->parse('student_synergy_form_block', 'student_synergy_form');
                    $this->setVar('student_synergy_tutor_block', '');
                } else {
                    $this->parse('student_synergy_tutor_block', 'student_synergy_tutor');
                    $this->setVar('student_synergy_form_block', '');
                }
            } else {
                if ($groupHandler->isStaff($user['uid']) && $groupHandler->isStaff()) {

                    $this->parse("timetable_link_block", "timetable_link");
                    $this->parse("portrait_block", "portrait");

                    $synInfo = $synUserHandler->getStaff($user['synergy_id']);

                    $this->setVar('EXTENSION', $synInfo['StaffExtension']);
                    $this->setVar('MOBILE', $synInfo['StaffMobilePhone']);
                    $this->setVar('DEPARTMENT', $synInfo['StaffDepartment']);
                    $this->setVar('ROOM', $synInfo['StaffRoom']);
                    $this->setVar('BUILDING', $synInfo['StaffBuilding']);
                    $this->setVar('CAMPUS', $synInfo['StaffCampusDescription']);
                    $this->setVar('CATEGORY', $synInfo['StaffCategoryDescription']);
                    $this->setVar('VOICEMAIL', $synInfo['StaffVoiceMail']);

                    $this->parse('staff_synergy_block', 'staff_synergy');
                }
            }
        }
    }

    /* Display eportfolio link only if it's enabled
      */
    if ($user['eportfolio_enabled']) {
        $this->parse('eportfolio_block', 'eportfolio');
    } else {
        $this->setVar('eportfolio_block', '');
    }

} else {
    error(2);
}