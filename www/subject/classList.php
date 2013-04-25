<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2002-2003 Lektec Consulting         *
 *                                                           *
 ************************************************************/
//ClassList object..displays class list with most recent visit

$groupHandler = new Group;

if ($groupHandler->isStaff()) {
    $userHandler = new User;
    $categoryHandler = new Category;
    $synergeticClass = new External_Class;


    $this->setFile(TEMPLATE_content, 'class/classList.htm');
    $this->setBlock(TEMPLATE_content, 'student_list', 'student_list_block');
    $this->setBlock(TEMPLATE_content, 'email_class', 'email_class_block');
    $this->setBlock('student_list', 'medical', 'medical_block');
    $this->setBlock('student_list', 'special', 'special_block');
    $this->setBlock('student_list', 'eportfolio', 'eportfolio_block');

    $this->setBlock('student_list', 'name_with_link', 'name_with_link_block');
    $this->setBlock('student_list', 'name_without_link', 'name_without_link_block');
    $this->setBlock('student_list', 'actions', 'actions_block');

    $this->setBlock(TEMPLATE_content, 'parent', 'parent_block');
    $this->setBlock(TEMPLATE_content, 'parent_email', 'parent_email_block');

    $this->setTitle('Class List');

    $code = urldecode(implode('/', $this->arg));

    if (stristr($code, ',')) {
        $codeArray = explode(',', $code);
        $category = $categoryHandler->getByCode($codeArray[0]);
    } else {
        $category = $categoryHandler->getByCode($code);
    }

    if (is_array($category)) {
        $this->setVar("SUBJECTNAME", $category['name'] . " ($code)");

        $classList = $synergeticClass->getStudents($code);

        $synergyIds = array();

        if (is_array($classList) && !empty($classList)) {
            foreach ($classList as $student) {

                /* Accumulate this for a giant parent-based bcc
                 */
                $synergyIds[] = $student['synergy_id'];

                $email = '';
                $emailaddr = '';

                $user = $userHandler->getBySynergy($student['synergy_id']);

                if (is_array($user)) {
                    $emailObj = new Email();
                    $emailaddr = $emailObj->formatEmailFromArray($user);

                    if ($emailaddr) {
                        $emaillink = "<a href=\"" . $_SESSION['mailhandle'] . $emailaddr . "\">Email</a>";
                        $classEmail .= $emailaddr . ", ";
                    }
                }

                $lastVisit = $categoryHandler->lastVisit($category['id'], $user['uid']);

                $studentOutput = array(
                    'SURNAME' => $student['lastname'],
                    'FIRSTNAME' => $student['firstname'],
                    'YEARLEVEL' => $student['yearlevel'],
                    'HOUSE' => $student['house'],
                    'LASTVISIT' => $lastVisit ? $lastVisit : 'Never',
                    'ID' => $user['uid'],
                    'SYNERGYID' => $student['synergy_id'],
                    'EMAIL' => $emailaddr ? $emaillink : '',
                    'EMAIL_ADDRESS' => $emailaddr,
                    'EMAIL_BTN' => $emailaddr ? sprintf(
                        '<a href="%s%s" title="Send email" class="btn email float-left"><span></span>Email</a>',
                        $_SESSION['mailhandle'], $emailaddr
                    ) : '',
                    'MEDICAL_ALERT' => $student['StudentMedicalAlert'],
                    'ON_OFF' => ++$j % 2 ? '' : 'dark',
                    'SELECT' => $emailaddr ? sprintf('<input id="c%s" name="c%s" type="checkbox" value="%s"/>', $j, $j, $emailaddr) : '',
                );

                $this->setVar($studentOutput);

                $student['StudentMedicalAlert'] = trim($student['StudentMedicalAlert']);
                $student['StudentSpecialNeedsFlag'] = trim($student['StudentSpecialNeedsFlag']);

                if (!empty($student['StudentMedicalAlert']) && Cfg::get('protect_medicalspecial') == 0) {
                    $this->parse('medical_block', 'medical');
                } else {
                    $this->setVar('medical_block', '');
                }

                if (!empty($student['StudentSpecialNeedsFlag']) && Cfg::get('protect_medicalspecial') == 0) {
                    $this->parse('special_block', 'special');
                } else {
                    $this->setVar('special_block', '');
                }

                /* If the user has an enabled eportfolio, enable link
                 */
                if ($user['eportfolio_enabled']) {
                    $this->parse('eportfolio_block', 'eportfolio');
                } else {
                    $this->setVar('eportfolio_block', '');
                }

                /* Parse out the name blocks: if they were found in Schoolbox,
				 * give the names links - otherwise leave them unlinked
				 */
                if (is_array($user)) {
                    $this->parse('name_with_link_block', 'name_with_link');
                    $this->setVar('name_without_link_block', '');
                    $this->parse('actions_block', 'actions');
                } else {
                    $this->parse('name_without_link_block', 'name_without_link');
                    $this->setVar('name_with_link_block', '');
                    $this->setVar('actions_block', '');
                }

                $this->parse("student_list_block", "student_list", true);
            }

            $emailObj = new Email();
            $email = $emailObj->formatEmailFromArray($_SESSION);

            if ($email) {
                $this->setVar("REPLYTO", $email);
                $this->setVar("CLASSEMAIL", $classEmail);
                $this->parse('email_class_block', 'email_class');
            } else {
                $this->setVar('email_class_block', '');
            }

            /* Parent bulk email
             */
            if (count($synergyIds)) {
                $allParentEmails = array();
                $allSynergyIds = implode(',', $synergyIds);

                $externalHandler = new External_User();
                $allParents = $externalHandler->getParentEmails($allSynergyIds);

                /* Uniquify emails
                */
                $emailAllParents = implode(', ', array_filter(array_unique($allParents)));

                $this->setVar(
                    array(
                        'REPLY_TO' => $emailObj->formatEmailFromArray($_SESSION),
                        'EMAIL_ALL_PARENTS' => $emailAllParents,
                    )
                );

		if (Cfg::get('email_all_parents') == 1) {
			$this->parse('parent_email_block', 'parent_email');
		} else {
			$this->setVar('parent_email_block', '');
		}
            }
        } else {
            $this->setVar('student_list_block', '');
            $this->setVar('parent_email_block', '');
            $this->setVar('email_class_block', '');
        }
    } else {
        $this->error(18);
    }


} else {
    error(19);
}
?>
