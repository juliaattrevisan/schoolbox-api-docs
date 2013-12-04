<?php

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
        $this->setBlock(TEMPLATE_content, "timetable_link", "timetable_link_block");
        $this->setBlock(TEMPLATE_content, "due_work", "due_work_block");
        $this->setBlock(TEMPLATE_content, "helpdesk", "helpdesk_block");
        $this->setBlock(TEMPLATE_content, "eportfolio", "eportfolio_block");
        $this->setBlock(TEMPLATE_content, "boarder_leave", "boarder_leave_block");
		$this->setBlock(TEMPLATE_content, 'user_calendar', 'user_calendar_block');
        $this->setBlock(TEMPLATE_content, 'modify', 'modify_block');
        $this->setBlock(TEMPLATE_content, "student_synergy_form", "student_synergy_form_block");
        $this->setBlock(TEMPLATE_content, "student_synergy_tutor", "student_synergy_tutor_block");
        $this->setBlock(TEMPLATE_content, 'contact_synergy', 'contact_synergy_block');
        $this->setBlock('contact_synergy', 'edit_emergency', 'edit_emergency_block');
        $this->setBlock('contact_synergy', 'edit_contact', 'edit_contact_block');
        $this->setBlock('contact_synergy', 'contact_details', 'contact_details_block');
        $this->setBlock('contact_synergy', 'contact_alert', 'contact_alert_block');
	    $this->setBlock('contact_details', 'contact_email', 'contact_email_block');
	    $this->setBlock('contact_details', 'contact_mobile', 'contact_mobile_block');
	    $this->setBlock('contact_details', 'contact_phone', 'contact_phone_block');
        $this->setBlock('contact_details', 'spouse_details', 'spouse_details_block');
	    $this->setBlock('contact_synergy', 'emergency_contact', 'emergency_contact_block');
	    $this->setBlock('spouse_details', 'spouse_email', 'spouse_email_block');
	    $this->setBlock('spouse_details', 'spouse_mobile', 'spouse_mobile_block');
	    $this->setBlock('spouse_details', 'spouse_phone', 'spouse_phone_block');
        $this->setBlock(TEMPLATE_content, 'medical', 'medical_block');
        $this->setBlock(TEMPLATE_content, 'subject', 'subject_block');
        $this->setBlock('subject', 'subject_row', 'subject_row_block');
        $this->setBlock('subject', 'subject_mail', 'subject_mail_block');
        $this->setBlock('subject_row', 'subject_item', 'subject_item_block');
	    $this->setBlock('subject_item', 'subject_teacher', 'subject_teacher_block');
	    $this->setBlock(TEMPLATE_content, 'group', 'group_block');
	    $this->setBlock('group', 'group_row', 'group_row_block');
	    $this->setBlock('group', 'group_mail', 'group_mail_block');
	    $this->setBlock('group_row', 'group_item', 'group_item_block');
	    $this->setBlock('group_item', 'group_teacher', 'group_teacher_block');
    }
	else if ($groupHandler->isParent($user['uid'])) {
        $this->setFile(TEMPLATE_content, 'user/parent.html');
        $this->setBlock(TEMPLATE_content, "eportfolio", "eportfolio_block");
        $this->setBlock(TEMPLATE_content, 'modify', 'modify_block');
        $this->setBlock(TEMPLATE_content, 'user_calendar', 'user_calendar_block');
        $this->setBlock(TEMPLATE_content, 'parent_synergy_form', 'parent_synergy_form_block');
        $this->setBlock('parent_synergy_form', 'parent_spouse', 'parent_spouse_block');
    } else {
        $this->setFile(TEMPLATE_content, 'user/staff.html');
        $this->setBlock(TEMPLATE_content, "portrait", "portrait_block");
        $this->setBlock(TEMPLATE_content, "timetable_link", "timetable_link_block");
        $this->setBlock(TEMPLATE_content, "eportfolio", "eportfolio_block");
        $this->setBlock(TEMPLATE_content, "due_work", "due_work_block");
        $this->setBlock(TEMPLATE_content, "staff_synergy", "staff_synergy_block");
        $this->setBlock(TEMPLATE_content, 'modify', 'modify_block');
        $this->setBlock(TEMPLATE_content, 'user_calendar', 'user_calendar_block');

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
        'modify_block' => '',
        'contact_synergy_block' => '',
        'due_work_block' => '',
        'helpdesk_block' => '',
        'parent_synergy_form_block' => '',
        'parent_spouse_block' => '',
        'staff_synergy_block' => '',
        'subject_block' => '',
        'subject_row_block' => '',
	    'group_block' => '',
	    'group_row_block' => '',
		'user_calendar_block' => ''
    );

    $this->setVar($blank);

	/* Calendar only exist for students' parents, staff, supers, and themselves
	 */
	$isParentOfChild = $groupHandler->isParentOfChild($user['uid']);
	$isSuperuser = $_SESSION['user']->getSuperuser();
	$isStaff = $groupHandler->isStaff();
	$isSelf = ($user['uid'] == $_SESSION['user']->getId());

	if ($isParentOfChild || $isSuperuser || $isStaff || $isSelf) {
		$now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

		$this->setVar(array(
			'USER_CALENDAR_CURRENTDATE' => $now->getUsingFormat('d-m-Y'),
		));

		$this->parse('user_calendar_block', 'user_calendar');
	}

	//Format email address link
    $emailObj = new Email();
    $emailAddress = trim($emailObj->formatEmailFromArray($user));

    /* Dont display emails of students to parents
      */
    $email = '';
    if ($emailAddress) {
        if ($groupHandler->isStudent($user['uid']) && $groupHandler->isParent() && !$groupHandler->isParentOfChild($user['uid'])) {
            $email = '';
        } else {
            /* If restrict_parent_emails and not a staff member, remove the email address */
            if ((Cfg::get('restrict_parent_emails') == '1') && $groupHandler->isParent($user['uid']) && !$groupHandler->isStaff()) {
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

    if ($_SESSION['su']) {
        $this->parse('modify_block', 'modify');
    }

    //advanced synergetic information
    if (Cfg::get('external_enable') && $user['synergy_id']) {

        $synUserHandler = new External_User();

        if ($groupHandler->isParent($user['uid']) && !$groupHandler->isStudent()) {
            $parent = $synUserHandler->getParent($user['synergy_id']);

            if ($parent['DirectoryIncludeFlag']) {
                $output = array(
                    'NAME' => Scrub::text($parent['Title'] . ' ' . $parent['Preferred'] . ' ' . $parent['Surname']),
                    'HOME_PHONE' => !$parent['SilentPhoneFlag'] ? '<a href="tel:'.Scrub::text($parent['HomePhone']).'">'.Scrub::text($parent['HomePhone']).'</a>' : '',
                    'MOBILE_PHONE' => '<a href="tel:'.Scrub::text($parent['MobilePhone']).'">'.Scrub::text($parent['MobilePhone']).'</a>',
                );

                $this->setVar($output);
                             
                if ($parent['SpouseDirectoryIncludeFlag'] && $parent['SpouseSurname']) {
                    $output = array(

                        'SPOUSE_NAME' => Scrub::text(
                            $parent['SpouseTitle'] . ' ' . $parent['SpousePreferred'] . ' ' . $parent['SpouseSurname']
                        ),
                        'SPOUSE_MOBILE' => '<a href="tel:'.Scrub::text($parent['SpouseMobilePhone']).'">'.Scrub::text($parent['SpouseMobilePhone']).'</a>',
                        'SPOUSE_EMAIL' => '<a href="' . $_SESSION['mailhandle'] . $parent['SpouseEmail'] . '\">' . $parent['SpouseEmail'] . '</a>',
                    );
                    
                    /* If restrict_parent_emails and not a staff member, remove the email address */
                    if ((Cfg::get('restrict_parent_emails') == '1') && $groupHandler->isParent($user['uid']) && !$groupHandler->isStaff()) {
                        $output['SPOUSE_EMAIL'] = '';
                    }
                    
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
            if ($groupHandler->isStudent($user['uid'])) {

	            //If student photo protected don't show other students
                $this->parse("portrait_block", "portrait");


	            if($groupHandler->isStaff() || $groupHandler->isParentOfChild( $user['uid']) || $_SESSION['id'] == $user['uid']) {
					//only allow staff to see student information or parent
		            $this->parse("portrait_block", "portrait");
		            $this->parse("timetable_link_block", "timetable_link");

					/* Hide Due Work link from parents, if Due Work access is restricted
					 */
					if ($groupHandler->isParent() && Config::get('restrict_parent_duework_access')) {
						$this->setVar('due_work_block', '');
					}
	                /* Allow students, staff and parents of the student to see information about
	                * due work. Also allow the current user if they view themselves in the
	                * search results.
	                */
	                else if ($groupHandler->isParent() && Cfg::get('site_location') == 'shore') {
	                    $this->setVar('due_work_block', '');
	                }
					else {
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

					$allSubjectLists = array();

					/* Group them up into manageable chunks because of phplib
					 * Dump subjects without a defined classType into a generic
					 * "Subjects" array element, otherwise use their provided
					 * classTypes instead
					 */
					if (is_array($subjectList) && count($subjectList)) {
						foreach($subjectList as $currentSubject) {
							$category = $catHandler->getByCode($currentSubject['code']);

							/* If the homepage doesn't exist or can't be found, don't
							 * link it in the Subject list
							 */
							if (is_numeric($category['id'])) {
								$childElement = array(
									'subject' => $currentSubject,
									'category' => $category,
									'categoryName' => is_array($category) ? $category['name'] : $currentSubject['name']
								);

								/* Subjects without a parent just get thrown together into
								 * a generic container
								 */
								if (!strlen($currentSubject['classType'])) {
									$allSubjectLists['Subjects'][] = $childElement;
								}
								else {
									$allSubjectLists[$currentSubject['classType']][] = $childElement;
								}
							}
						}
					}

					/* Expel each subject grouped by its type
					 */
					foreach($allSubjectLists as $key => $currentSubjectList) {
						$emailAll = array();

						$this->setVar(array(
							'SUBJECT_CLASSTYPE' => $key
						));

						/* For all the classes in the current subject
						 */
						if (is_array($currentSubjectList) && count($currentSubjectList)) {
							$listItems = count($currentSubjectList)>1 ? ceil(count($currentSubjectList)/2) : 1;
							$i = 1;

							foreach($currentSubjectList as $currentSubject) {


								$this->setVar(array(
									'subject_teacher_block' => ''
								));

								if ((Cfg::get('show_all_subjects') == 1) || $currentSubject['category']['id'] ) {
									$this->setVar(array(
										'SUBJECT_NAME' => Scrub::text($currentSubject['categoryName']),
										'SUBJECT_URL' => is_array($currentSubject['category']) && $groupHandler->getPermission($currentSubject['category']['id']  ) >= READ_ACCESS ? "/homepage/{$currentSubject['subject']['code']}": '#',
									));

									/* Fetch teacher names
									 */
									if ($subject['campusCode']) {
										$code = $currentSubject['subject']['campusCode'].'/'.$currentSubject['subject']['code'];
									}
									else {
										$code = $currentSubject['subject']['code'];
									}

									$teacherList = $synClass->getTeacherName($code);
									$teacherOutput = array();

									if (is_array($teacherList) && count($teacherList)) {
										foreach ($teacherList as $teacherSynID) {
											$teacher = $userHandler->getBySynergyAndGroup($teacherSynID, $groupHandler->getStaffGroupTypeIds());

											if (is_array($teacher)) {
												$teacherName = $userHandler->formatName($teacher);

												$emailObj = new Email();
												$emailAddress = $emailObj->formatEmailFromArray($teacher);
												$emailAll[] = $emailAddress;

												$this->setVar(array(
													'SUBJECT_TEACHER_ID' => $teacher['uid'],
													'SUBJECT_TEACHER_EXTERNAL_ID' => $teacher['synergy_id'],
													'SUBJECT_TEACHER_NAME' => $teacherName,
													'SUBJECT_TEACHER_EMAIL' => $_SESSION['mailhandle'] . $emailAddress,
												));

												$this->parse('subject_teacher_block', 'subject_teacher', true);
											}
										}
									}

									$this->parse('subject_item_block', 'subject_item', true);

									/* Clear children after parsing out the parent
									 */
									$this->setVar(array(
										'subject_teacher_block' => ''
									));

									/* Distribute half n' half
									 */
									if ($i % $listItems == 0) {
										$this->parse('subject_row_block', 'subject_row', true);

										/* Clear children after parsing out the parent
										 */
										$this->setVar(array(
											'subject_item_block' => ''
										));

										$i = 0;
									}
									else {
										$i++;
									}
								}
							}

							/* Output email all subject teachers */
							if ($groupHandler->isStaff()) {
								$this->setVar("REPLYTO", $emailObj->formatEmailFromArray($_SESSION));
								$this->setVar("EMAILALL", implode(',', $emailAll));
								$this->parse("subject_mail_block", "subject_mail");
							}
							else {
								$this->setVar('subject_mail_block', '');
							}
						}

						$this->parse('subject_block', 'subject', true);

						/* Clear children after parsing out the parent
						 */
						$this->setVar(array(
							'subject_row_block' => '',
							'subject_item_block' => '',
						));
					}

		            /* Output group list */
		            $groupList = $groupHandler->involvedInCategoryList($user['uid'], false);

		            if (is_array($groupList)) {
			            $i = 1;
			            $listItems = count($groupList)>1 ? floor(count($groupList)/2) : 1;

			            foreach ($groupList as $group) {
				            $this->setVar('group_teacher_block', '');

				            /* Fetch admin names */
			                $owner = $groupHandler->getOwner($group['id']);

				            if (is_array($owner)) {
					            $ownerName = $userHandler->formatName($owner);

					            $emailObj = new Email();
					            $emailAddress = $emailObj->formatEmailFromArray($owner);

					            $this->setVar( array( 'SUBJECT_TEACHER_ID' => $owner['uid'],
						                           'SUBJECT_TEACHER_EXTERNAL_ID' => $owner['synergy_id'],
						                           'SUBJECT_TEACHER_NAME' => $ownerName,
						                           'SUBJECT_TEACHER_EMAIL' => $_SESSION['mailhandle'] . $emailAddress,
					                           ));
					            $this->parse('group_teacher_block', 'group_teacher',true);

				            }


				            $output = array(
					            'GROUP_NAME' => Scrub::Text($group['name']),
					            'GROUP_URL' => is_array($group) && $groupHandler->getPermission( $group['id']  ) >= READ_ACCESS ? "/homepage/{$group['id']}": '#',
				            );



				            $this->setVar($output);
				            $this->parse('group_item_block', 'group_item', true);

				            if ($i % $listItems == 0) {
					            $this->parse('group_row_block', 'group_row', true);
					            $this->setVar('group_item_block', '');
				            }
				            $i++;
			            }

			            if (($i - 1) % $listItems != 0) {

				            $this->parse('group_row_block', 'group_row', true);
			            }
			            $this->parse('group_block', 'group');
		            }

	                $studentContactList = $synUserHandler->getStudentContactList($user['synergy_id']);
	                if (is_array($studentContactList)) {
	                    $prevContact['StudentContactID'] = 1;
	                    foreach ($studentContactList as $studentContact) {
	                        if ($prevContact['StudentContactID'] != $studentContact['StudentContactSpouseID'] && ( (int)$studentContact['StudentContactID'] == (int)$_SESSION['sid'] || $studentContact['StudentContactSpouseID'] == $_SESSION['sid'] || $groupHandler->isStaff() || $_SESSION['su'])) {
	                            $j++;

		                        if ($studentContact['StudentContactDefaultEmail']) {
		                            $contactEmail = $_SESSION['mailhandle'] . $studentContact['StudentContactDefaultEmail'];
		                        }

	                            if ($studentContact['StudentContactSpouseDefaultEmail']) {
	                                $spouseEmail = $_SESSION['mailhandle'] . $studentContact['StudentContactSpouseDefaultEmail'];
	                            }

		                        $parent = $userHandler->getBySynergy($studentContact['StudentContactID']);
		                        $spouse = $userHandler->getBySynergy($studentContact['StudentContactSpouseID']);

		                        $address_fields = array_filter(
			                        array_map(
				                        'trim',
				                        array(
					                        $studentContact['StudentContactAddress1'],
					                        $studentContact['StudentContactAddress2'],
					                        $studentContact['StudentContactAddress3'],
				                        )
			                        ),
			                        'strlen'
		                        );

	                            $output = array(
	                                'CONTACT_NAME' => Scrub::text(
	                                    $studentContact['StudentContactTitle'] . ' ' . $studentContact['StudentContactPreferred'] . ' ' . $studentContact['StudentContactSurname']
	                                ),
		                            'CONTACT_ID' => $parent['uid'],
		                            'CONTACT_ROLE' => Scrub::text($studentContact['StudentContactRelation']),
	                                'CONTACT_ADDRESS' => Scrub::text(
	                                    implode(' ', $address_fields)
	                                ) . '<br>' . Scrub::text(
	                                    $studentContact['StudentContactSuburb'] . ', ' . $studentContact['StudentContactState'] . ' ' . $studentContact['StudentContactPostCode']
	                                ),
	                                'CONTACT_HOME_PHONE' => '<a href="tel:'.Scrub::text($studentContact['StudentContactPhoneActual']).'">'.Scrub::text($studentContact['StudentContactPhoneActual']).'</a>',
	                                'CONTACT_MOBILE_PHONE' => '<a href="tel:'.Scrub::text($studentContact['StudentContactMobilePhone']).'">'.Scrub::text($studentContact['StudentContactMobilePhone']).'</a>',
	                                'CONTACT_OCCUP_PHONE' => '<a href="tel:'.Scrub::text($studentContact['StudentContactOccupPhone']).'">'.Scrub::text($studentContact['StudentContactOccupPhone']).'</a>',
	                                'CONTACT_EMAIL' =>  $contactEmail,
	                                'CONTACT_NAME_EMAIL' => $contactName,
	                                'SPOUSE_NAME' => Scrub::text(
	                                    $studentContact['StudentContactSpouseTitle'] . ' ' . $studentContact['StudentContactSpousePreferred'] . ' ' . $studentContact['StudentContactSpouseSurname']
	                                ),
		                            'SPOUSE_ID' => $spouse['uid'],
		                            'SPOUSE_ROLE' => Scrub::text($studentContact['StudentContactSpouseRelation']),
	                                'SPOUSE_MOBILE_PHONE' => '<a href="tel:'.Scrub::text($studentContact['StudentContactSpouseMobilePhone']).'">'.Scrub::text($studentContact['StudentContactSpouseMobilePhone']).'</a>',
	                                'SPOUSE_OCCUP_PHONE' => '<a href="tel:'.Scrub::text($studentContact['StudentContactSpouseOccupPhone']).'">'.Scrub::text($studentContact['StudentContactSpouseOccupPhone']).'</a>',
	                                'SPOUSE_EMAIL' => $spouseEmail,
	                            );
		                        //
		                        // Links to /search/user page (if this user has one)
		                        $output['CONTACT_LINK'] = $output['CONTACT_ID'] ? ('/search/user/'.$output['CONTACT_ID']) : '#';
		                        $output['SPOUSE_LINK']  = $output['SPOUSE_ID']  ? ('/search/user/'.$output['SPOUSE_ID'])  : '#';
	                            $this->setVar($output);

		                        /* Output each of the block for contact details */
		                        if($contactEmail)
			                        $this->parse('contact_email_block','contact_email');
		                        else
			                        $this->setVar('contact_email_block','');

								if($studentContact['StudentContactMobilePhone'])
									$this->parse('contact_mobile_block','contact_mobile');
								else
									$this->setVar('contact_mobile_block','');

		                        if($studentContact['StudentContactOccupPhone'])
			                        $this->parse('contact_phone_block','contact_phone');
		                        else
			                        $this->setVar('contact_phone_block','');


								/* Has spouse, output spouse */
	                            if (trim($studentContact['StudentContactSpouseTitle']) || trim($studentContact['StudentContactSpousePreferred'])) {

		                            /* Output each of the block for contact details */
		                            if($spouseEmail)
			                            $this->parse('spouse_email_block','spouse_email');
		                            else
			                            $this->setVar('spouse_email_block','');

		                            if($studentContact['StudentContactSpouseMobilePhone'])
			                            $this->parse('spouse_mobile_block','spouse_mobile');
		                            else
			                            $this->setVar('spouse_mobile_block','');

		                            if($studentContact['StudentContactSpouseOccupPhone'])
			                            $this->parse('spouse_phone_block','spouse_phone');
		                            else
			                            $this->setVar('spouse_phone_block','');


	                                $this->parse('spouse_details_block', 'spouse_details');
	                            }
	                            else {
	                                $this->setVar('spouse_details_block','');
	                            }

	                            $this->parse('contact_details_block', 'contact_details', true);

		                        $prevContact = $studentContact;
	                        }
	                    }
	                }

		            /* Output emergency contacts */
		            $this->setVar('emergency_contact_block','');
		            for($x = 1; $x <= 3; $x++) {
			            $index = $x == 1?'':$x;

			            if($synInfo['StudentEmergency'.$index.'Name']) {
				            $this->setVar(array(
		                          'EMERGENCY_NAME' => Scrub::text($synInfo['StudentEmergency'.$index.'Name']),
		                          'EMERGENCY_PHONE' => '<a href="tel:'.Scrub::text($synInfo['StudentEmergency'.$index.'Phone']).'">'.Scrub::text($synInfo['StudentEmergency'.$index.'Phone']).'</a>',
		                          'EMERGENCY_ALT_PHONE' => '<a href="tel:'.Scrub::text($synInfo['StudentEmergency'.$index.'PhoneAlt']).'">'.Scrub::text($synInfo['StudentEmergency'.$index.'PhoneAlt']).'</a>',
				                          ));
			                $this->parse('emergency_contact_block', 'emergency_contact', true);
			            }
		            }

	                if ($groupHandler->isStaff() || (Cfg::get('site_location') != 'mgs' && $groupHandler->isParentOfChild($user['uid']))) {
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
	                    $this->parse('edit_emergency_block', 'edit_emergency');
	                    $this->parse('edit_contact_block', 'edit_contact');
	                }

	                /* Display boarder leave for kings */
	                if (Cfg::get('boarder_leave_enabled') == '1') {
	                    if (($groupHandler->isStaff() || $_SESSION['id'] == $user['uid']) AND $user['residential_house']) {
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
	            }
            } else if ($groupHandler->isStaff($user['uid']) && $groupHandler->isStaff()) {

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
                if ($_SESSION['su']) {
                    $this->parse('modify_block', 'modify');
                }
            }
        }
    }

	/* ePortfolio link
	*/
	$authenticator = new \Eportfolio_Authenticator($_SESSION['id'], $user['uid'], 'profile');

	if ($authenticator->canView()) {
		$this->parse('eportfolio_block', 'eportfolio');
	}
	else {
        $this->setVar('eportfolio_block', '');
    }

} else {
    error(2);
}