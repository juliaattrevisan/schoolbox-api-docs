<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 ************************************************************/
//ClassList object..displays class list with most recent visit

use Schoolbox\Resource\Folder\Log\Model as Log;
use \Schoolbox\Breadcrumbs;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;

$groupHandler = new Group;

$categoryMapper = new \Schoolbox\Resource\CategoryMapper();
$categoryLogMapper = new \Schoolbox\Resource\CategoryLogMapper();
$userMapper = new \Schoolbox\UserMapper();

if ($groupHandler->isStaff() || $groupHandler->isSuperuser()) {
    $userHandler = new User;
    $synergeticClass = new External_Class;

    $this->setFile(TEMPLATE_content, 'class/classList.html');
    $this->setBlock(TEMPLATE_content, 'student_list', 'student_list_block');
    $this->setBlock(TEMPLATE_content, 'email_class', 'email_class_block');
    $this->setBlock(TEMPLATE_content, 'select_all', 'select_all_block');
    $this->setBlock('student_list', 'select_student', 'select_student_block');
    $this->setBlock('student_list', 'medical', 'medical_block');
    $this->setBlock('student_list', 'special', 'special_block');
    $this->setBlock('student_list', 'name_with_link', 'name_with_link_block');
    $this->setBlock('student_list', 'name_without_link', 'name_without_link_block');
    $this->setBlock('student_list', 'actions', 'actions_block');
    $this->setBlock('actions', 'eportfolio', 'eportfolio_block');
    $this->setBlock('actions', 'pastoral_care', 'pastoral_care_block');
    $this->setBlock('actions', 'has_email', 'has_email_block');
    $this->setBlock(TEMPLATE_content, 'parent', 'parent_block');
    $this->setBlock(TEMPLATE_content, 'parent_email', 'parent_email_block');

    $this->setTitle('Class List');

    $code = urldecode(implode('/', $this->arg));

    if (stristr($code, ',')) {
        $codeArray = explode(',', $code);

        $val = $codeArray[0];
    } else {
        $val = $code;
    }

    try {
        $category = $categoryMapper->findByCode($val);
    } catch (\Exception $e) {
        $this->error(18);
    }

    $canEmail = false;
    $email    = '';

    if (Cfg::get('mail_enable')) {
        $emailObj = new Email();
        $email = $emailObj->formatEmailFromArray($_SESSION);

        if ($email) {
            $canEmail = true;
        }
    }

    $this->setVar(array(
        'SUBJECTNAME' => "{$category->getName()} ({$code})",
        'CLASS_CODE' => $code,
        'select_all_block' => '',
    ));

    if ($canEmail) {
        $this->parse('select_all_block', 'select_all');
    }

    $classList = $synergeticClass->getStudents($code);

    $synergyIds = array();

    if (is_array($classList) && !empty($classList)) {
        foreach ($classList as $student) {
            try {
                $user = $userMapper->findOneByColumn('synergy_id', $student['synergy_id']);
            } catch (\Exception $e) {
                $user = new \Schoolbox\User();
            }

            $emailaddr = $user->getEmail();

            if (strlen($emailaddr)) {
                $emailLink = "<a href=\"" . $_SESSION['mailhandle'] . $user->getEmail() . "\">Email</a>";
            } else {
                $emailLink = '';
            }

            $lastVisit = $categoryLogMapper->lastVisit($category, $user)->first();

            $this->setVar(array(
                'SURNAME' => $student['lastname'],
                'FIRSTNAME' => $student['firstname'],
                'YEARLEVEL' => $student['yearlevel'],
                'HOUSE' => Scrub::text($student['house']),
                'LASTVISIT' => $lastVisit instanceof Log ? $lastVisit->getLastAccessTime()->getShort() : 'Never',
                'ID' => $user->getId(),
                'SYNERGYID' => $student['synergy_id'],
                'EMAIL' => $emailLink,
                'EMAIL_ADDRESS' => $user->getEmail(),
                'MEDICAL_ALERT' => $student['StudentMedicalAlert'],
                'ON_OFF' => ++$j % 2 ? '' : 'dark',
                'SELECT' => $user->getEmail() ? sprintf('<input id="c%s" name="c%s" type="checkbox" value="%s"/>', $j, $j, $emailaddr) : '',
            ));

            /* "Send Email" button
             */
            if ($user->getEmail()) {
                $this->setVar(array(
                    'HAS_EMAIL_URL' => $_SESSION['mailhandle'] . $emailaddr
                ));

                $this->parse('has_email_block', 'has_email');
            }
            else {
                $this->setVar(array(
                    'has_email_block' => ''
                ));
            }

            // Show pastoral care link to Synergetic
            if($groupHandler->isStaff() && Config::get('external_type') == 'synergetic' && Config::get('external_staff_portal') ) {
                $this->setVar('SYNWEB_URL', Config::get('external_staff_portal'));
                $this->parse('pastoral_care_block', 'pastoral_care');
            }
            else {
                $this->setVar('pastoral_care_block', '');
            }

            $this->setVar(array(
                'select_student_block' => '',
            ));

            if ($canEmail) {
                $this->parse('select_student_block', 'select_student');
            }

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
            if ($user->getEportfolioEnable()) {
                $this->parse('eportfolio_block', 'eportfolio');
            } else {
                $this->setVar(array(
                    'eportfolio_block' => ''
                ));
            }

            /* Parse out the name blocks: if they were found in Schoolbox,
             * give the names links - otherwise leave them unlinked
             */
            if (is_numeric($user->getId())) {
                $this->parse('name_with_link_block', 'name_with_link');

                $this->setVar(array(
                    'name_without_link_block' => ''
                ));

                $this->parse('actions_block', 'actions');
            } else {
                $this->parse('name_without_link_block', 'name_without_link');

                $this->setVar(array(
                    'name_with_link_block' => '',
                    'actions_block' => '',
                ));
            }

            $this->parse("student_list_block", "student_list", true);

            /* Clear children after parsing out the parent
             */
            $this->setVar(array(
                'has_email_block' => '',
                'eportfolio_block' => '',
                'pastoral_care_block' => '',
            ));
        }

        // Show the email view/select forms
        $this->setVar(array(
            'email_class_block' => '',
            'parent_email_block' => '',
        ));

        if ($canEmail && $email) {
            $this->setVar(array(
                'REPLYTO' => $email
            ));

            $this->parse('email_class_block', 'email_class');

            /* Emailing parents is a configuration
             */
            if ((Cfg::get('email_all_parents') == 1)) {
                $this->parse('parent_email_block', 'parent_email');
            }
        }
    } else {
        $this->setVar(array(
            'student_list_block' => '',
            'parent_email_block' => '',
            'email_class_block' => '',
        ));
    }

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $category->getName(),
            'href' => "/homepage/{$category->getId()}",
            'class' => '',
        ],
        [
            'text' => 'Class List',
            'href' => '',
            'class' => 'active',
        ]
    ]);
} else {
    error(19);
}
