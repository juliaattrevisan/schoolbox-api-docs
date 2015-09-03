<?php

use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder;

//require_once 'include/permission.php';

//display user description page

/**
 * Staff rendering, extracted to a method.
 * @param SitePage                 $page
 * @param array                    $user
 * @param External_User            $synUserHandler
 * @param UserMapper               $userMapper
 * @param Eportfolio_Authenticator $authenticator
 */
function renderStaff(
    SitePage $page,
    $user,
    External_User $synUserHandler,
    UserMapper $userMapper,
    Eportfolio_Authenticator $authenticator
) {

    $externalUser = $synUserHandler->getStaff($user['synergy_id']);
    // TwigLib
    $page->setFile(TEMPLATE_content, 'output.html');
    $page->setVar(
        'OUTPUT',
        $page->getTwig()->render(
            'user/staff.twig',
            [
                'session'         => $_SESSION,
                'user'            => $userMapper->findById($user['uid']),
                'externalUser'    => $externalUser,
                'eportfolioAuth'  => $authenticator->canView(),
                'now'             => Alaress_Date_Format::now(),
            ]
        )
    );
}

/**
 * Student rendering, extracted to a method.
 *
 * @param SitePage                 $page
 * @param array                    $user
 * @param Group                    $groupHandler
 * @param External_User            $synUserHandler
 * @param External_Class           $classHandler
 * @param UserMapper               $userMapper
 * @param Folder\Mapper            $folderMapper
 * @param Eportfolio_Authenticator $authenticator
 */
function renderStudent(
    SitePage $page,
    $user,
    Group $groupHandler,
    External_User $synUserHandler,
    External_Class $classHandler,
    UserMapper $userMapper,
    Folder\Mapper $folderMapper,
    Eportfolio_Authenticator $authenticator
) {
    // Handle parent/child relations here for now
    $isParentOfChild = $groupHandler->isParentOfChild($user['uid']);
    $externalUser = $synUserHandler->getStudent($user['synergy_id']);
    $externalUser['AlertMessages'] = preg_split(
        '/[\\n\\r]+/',
        $externalUser['AlertMessage'],
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    // Load the list of subjects, and their teacher.
    // Then group by subject type?
    $subjects      = [];
    $splitSubjects = \Config::get('mysubjects_split');
    $teacherRoles  = $groupHandler->getStaffGroupTypeIds();
    foreach ($synUserHandler->getSubjectList($user['synergy_id'], $user['uid']) as $subject) {
        $group = $splitSubjects ? $subject['classType'] : 'Subject';
        // Load a bunch of category and teacher info too
        try {
            $subject['folder'] = $folderMapper->findByCode($subject['code']);
        } catch (\Exception $e) {
            if (\Config::get('show_all_subjects')) {
                $code = new Folder\Code\Model();
                $code->setCode($subject['code']);
                $subject['folder'] = new Folder\Model();
                $subject['folder']->setName($subject['name'])->addCode($code);
            } else {
                continue;
            }
        }
        $subject['teachers'] = $userMapper->findByColumnList([
            'synergy_id' => $classHandler->getTeacherName($subject['code']),
            'group_id'   => $teacherRoles,
        ]);
        $subjects[$group][] = $subject;
    }

    // Load the list of groups, and their owner.
    $groups = $groupHandler->involvedInCategoryList($user['uid'], false);
    foreach ($groups as &$group) {
        $owner = $groupHandler->getOwner($group['id']);
        if (is_array($owner)) {
            $group['owner'] = $userMapper->findById($owner['uid']);
        }
    }
    unset($group);

    // Load users for form teacher, tutor, etc.
    $formTeacher = null;
    if ($externalUser['StudentFormStaffName'] && $externalUser['TeacherFormID']) {
        try {
            $formTeacher = $userMapper->findByExternalId($externalUser['TeacherFormID']);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $formTeacher = new \Schoolbox\User();
            $formTeacher->setLastname($externalUser['StudentFormStaffName']);
        }
    }

    $tutorTeacher = null;
    if ($externalUser['StudentTutorName'] && $externalUser['TeacherTutorID']) {
        try {
            $tutorTeacher = $userMapper->findByExternalId($externalUser['TeacherTutorID']);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $tutorTeacher = new \Schoolbox\User();
            $tutorTeacher->setLastname($externalUser['StudentTutorName']);
        }
    }

    $hohTeacher = null;
    if ($externalUser['StudentHeadOfHouse'] && $externalUser['HeadOfHouseID']) {
        try {
            $hohTeacher = $userMapper->findByExternalId($externalUser['HeadOfHouseID']);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $hohTeacher = new \Schoolbox\User();
            $hohTeacher->setLastname($externalUser['StudentHeadOfHouse']);
        }
    }

    $coordTeacher = null;
    if ($externalUser['StudentYearLevelCoordinator'] && $externalUser['YearLevelCoordID']) {
        try {
            $coordTeacher = $userMapper->findByExternalId($externalUser['YearLevelCoordID']);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $coordTeacher = new \Schoolbox\User();
            $coordTeacher->setLastname($externalUser['StudentYearLevelCoordinator']);
        }
    }

    // Load contact's user IDs
    $contacts = [];
    foreach ($synUserHandler->getStudentContactList($user['synergy_id']) as $contact) {
        $main = null;
        try {
            $main = $userMapper->findByExternalId($contact['StudentContactID']);
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop
        }
        $spouse = null;
        try {
            $spouse = $userMapper->findByExternalId($contact['StudentContactSpouseID']);
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop
        }
        $contact['main']   = $main;
        $contact['spouse'] = $spouse;
        $contacts[]        = $contact;
    }

    // TwigLib
    // TODO make preg_replace available to Twig
    // TODO make session contents available to Twig by default, either through whole _SESSION or Aura
    // TODO make $now available to Twig by default
    $page->setFile(TEMPLATE_content, 'output.html');
    $page->setVar(
        'OUTPUT',
        $page->getTwig()->render(
            'user/student.twig',
            [
                'session'         => $_SESSION,
                'user'            => $userMapper->findById($user['uid']),
                'externalUser'    => $externalUser,
                'eportfolioAuth'  => $authenticator->canView(),
                'isParentOfChild' => $isParentOfChild,
                'now'             => Alaress_Date_Format::now(),
                'boarderType'     => $synUserHandler->determineBoarderType($externalUser['StudentBoarder']),
                'contacts'        => $contacts,
                'subjects'        => $subjects,
                'groups'          => $groups,
                'formTeacher'     => $formTeacher,
                'tutorTeacher'    => $tutorTeacher,
                'hohTeacher'      => $hohTeacher,
                'coordTeacher'    => $coordTeacher,
            ]
        )
    );
}

if (is_numeric($this->arg[0])) {
    $this->setTitle('Address Book');

    $userHandler  = new User();
    $groupHandler = new Group();

    $user = $userHandler->getByID($this->arg[0]);

    if (!is_numeric($user['uid'])) {
        error(31);
    }

    if ($groupHandler->isStudent($user['uid'])) {
        // Twig
    } elseif ($groupHandler->isParent($user['uid'])) {
        $this->setFile(TEMPLATE_content, 'user/parent.html');
        $this->setBlock(TEMPLATE_content, "eportfolio", "eportfolio_block");
        $this->setBlock(TEMPLATE_content, 'modify', 'modify_block');
        $this->setBlock(TEMPLATE_content, 'user_calendar', 'user_calendar_block');
        $this->setBlock(TEMPLATE_content, 'parent_synergy_form', 'parent_synergy_form_block');
        $this->setBlock('parent_synergy_form', 'parent_spouse', 'parent_spouse_block');
    } else {
        // Twig
    }

    $blank = array(
        'contact_synergy_block'       => '',
        'edit_emergency_block'        => '',
        'edit_contact_block'          => '',
        'contact_details_block'       => '',
        'student_synergy_tutor_block' => '',
        'student_synergy_form_block'  => '',
        'medical_block'               => '',
        'contact_alert_block'         => '',
        'spouse_details_block'        => '',
        'portrait_block'              => '',
        'timetable_link_block'        => '',
        'eportfolio_block'            => '',
        'boarder_leave_block'         => '',
        'modify_block'                => '',
        'due_work_block'              => '',
        'grades_button_block'         => '',
        'parent_synergy_form_block'   => '',
        'parent_spouse_block'         => '',
        'staff_synergy_block'         => '',
        'group_block'                 => '',
        'group_row_block'             => '',
        'user_calendar_block'         => '',
        'pastoral_care_block'         => '',

    );

    $this->setVar($blank);

    /* Calendar only exist for students' parents, staff, supers, and themselves
     */
    $isParentOfChild = $groupHandler->isParentOfChild($user['uid']);
    $isSuperuser     = $_SESSION['user']->getSuperuser();
    $isStaff         = $groupHandler->isStaff();
    $isSelf          = ($user['uid'] == $_SESSION['user']->getId());

    if ($isParentOfChild || $isSuperuser || $isStaff || $isSelf) {
        $now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

        $this->setVar(
            array(
                'USER_CALENDAR_CURRENTDATE' => $now->getURLDate(),
            )
        );

        if (!$groupHandler->isStudent($user['uid'])) {
            $this->parse('user_calendar_block', 'user_calendar');
        }
    }

    //Format email address link
    $emailObj     = new Email();
    $emailAddress = trim($emailObj->formatEmailFromArray($user));

    /* Dont display emails of students to parents
      */
    $email = '';
    if ($emailAddress) {
        if ($groupHandler->isStudent($user['uid']) && $groupHandler->isParent() && !$groupHandler->isParentOfChild(
            $user['uid']
        )
        ) {
            $email = '';
        } else {
            /* If restrict_parent_emails and not a staff member, remove the email address */
            if ((Cfg::get('restrict_parent_emails') == '1') && $groupHandler->isParent(
                $user['uid']
            ) && !$groupHandler->isStaff()
            ) {
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
        'NAME'          => $userHandler->formatName($user),
        'ID'            => $this->arg[0],
        'CAMPUS_ID'     => $user['campus'],
        'EMAIL_ADDRESS' => $emailAddress,
        'EMAIL'         => $email,
        'POSITION'      => Scrub::text($user['gname']),
        'HOMEPAGE'      => '',
        'SYNERGY_ID'    => $user['synergy_id'],
    );

    $this->setVar($outputUser);

    if ($_SESSION['su'] && !$groupHandler->isStudent($user['uid'])) {
        $this->parse('modify_block', 'modify');
    }

    //advanced synergetic information
    if (Cfg::get('external_enable') && $user['synergy_id']) {
        $synUserHandler = new External_User();

        if ($groupHandler->isParent($user['uid']) && $groupHandler->isStaff()) {
            $parent = $synUserHandler->getParent($user['synergy_id']);

            if ($parent['DirectoryIncludeFlag']) {
                $output = array(
                    'NAME'         => Scrub::text($parent['Title'] . ' ' . $parent['Preferred'] . ' ' . $parent['Surname']),
                    'HOME_PHONE'   => !$parent['SilentPhoneFlag']
                            ? '<a href="tel:' . Scrub::text($parent['HomePhone']) . '">' . Scrub::text($parent['HomePhone']) . '</a>'
                            : '',
                    'MOBILE_PHONE' => '<a href="tel:' . Scrub::text($parent['MobilePhone']) . '">' . Scrub::text($parent['MobilePhone']) . '</a>',
                );

                $this->setVar($output);

                if ($parent['SpouseDirectoryIncludeFlag'] && $parent['SpouseSurname']) {
                    $output = array(

                        'SPOUSE_NAME'   => Scrub::text(
                            $parent['SpouseTitle'] . ' ' . $parent['SpousePreferred'] . ' ' . $parent['SpouseSurname']
                        ),
                        'SPOUSE_MOBILE' => '<a href="tel:' . Scrub::text(
                            $parent['SpouseMobilePhone']
                        ) . '">' . Scrub::text($parent['SpouseMobilePhone']) . '</a>',
                        'SPOUSE_EMAIL'  => '<a href="' . $_SESSION['mailhandle'] . $parent['SpouseEmail'] . '\">' . $parent['SpouseEmail'] . '</a>',
                    );

                    /* If restrict_parent_emails and not a staff member, remove the email address */
                    if ((Cfg::get('restrict_parent_emails') == '1') && $groupHandler->isParent(
                        $user['uid']
                    ) && !$groupHandler->isStaff()
                    ) {
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
                renderStudent(
                    $this,
                    $user,
                    $groupHandler,
                    $synUserHandler,
                    new External_Class(),
                    new UserMapper(),
                    new Folder\Mapper(),
                    new Eportfolio_Authenticator($_SESSION['user']->getId(), $user['uid'], 'profile')
                );
            } elseif ($groupHandler->isStaff($user['uid']) && $groupHandler->isStaff()) {
                renderStaff(
                    $this,
                    $user,
                    $synUserHandler,
                    new UserMapper(),
                    new Eportfolio_Authenticator($_SESSION['user']->getId(), $user['uid'], 'profile')
                );
//                $this->parse("timetable_link_block", "timetable_link");
//                $this->parse("portrait_block", "portrait");
//
//                $synInfo = $synUserHandler->getStaff($user['synergy_id']);
//
//                $this->setVar('EXTENSION', $synInfo['StaffExtension']);
//                $this->setVar('MOBILE', $synInfo['StaffMobilePhone']);
//                $this->setVar('DEPARTMENT', $synInfo['StaffDepartment']);
//                $this->setVar('ROOM', $synInfo['StaffRoom']);
//                $this->setVar('BUILDING', $synInfo['StaffBuilding']);
//                $this->setVar('CAMPUS', $synInfo['StaffCampusDescription']);
//                $this->setVar('CATEGORY', $synInfo['StaffCategoryDescription']);
//                $this->setVar('VOICEMAIL', $synInfo['StaffVoiceMail']);
//
//                $this->parse('staff_synergy_block', 'staff_synergy');
//                if ($_SESSION['su']) {
//                    $this->parse('modify_block', 'modify');
//                }
            }
        }
    } else {
        //Remove subjects from output
        $renderer = new \Schoolbox\SubjectList\UserProfile($this, array());
        $renderer->render();
    }

    /* ePortfolio link
    */
    if (!$groupHandler->isStudent($user['uid'])) {
        $authenticator = new \Eportfolio_Authenticator($_SESSION['id'], $user['uid'], 'profile');
        if ($authenticator->canView()) {
            $this->parse('eportfolio_block', 'eportfolio');
        } else {
            $this->setVar('eportfolio_block', '');
        }
    }

} else {
    error(2);
}
